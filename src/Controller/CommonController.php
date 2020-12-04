<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Form\ContactType;
use App\Services\ContactService;
use App\Services\PageService;
use App\Services\SeoService;
use App\Services\SiteService;
use Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ErrorController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
class CommonController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    /**
     * @var Environment
     */
    private $twig;
    /**
     * @var MailerInterface
     */
    private $mailer;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var PageService
     */
    private $pageService;
    /**
     * @var SiteService
     */
    private $siteService;
    /**
     * @var ContactService
     */
    private $contactService;
    /**
     * @var SeoService
     */
    private \App\Services\SeoService $seoService;
    /**
     * @var HtmlErrorRenderer
     */
    private $errorRenderer;
    public function __construct(\Twig\Environment $twig, \Symfony\Component\Mailer\MailerInterface $mailer, \Symfony\Contracts\Translation\TranslatorInterface $translator, \App\Services\PageService $pageService, \App\Services\SiteService $siteService, \App\Services\ContactService $contactService, \App\Services\SeoService $seoService, \Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer $errorRenderer)
    {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->pageService = $pageService;
        $this->siteService = $siteService;
        $this->contactService = $contactService;
        $this->seoService = $seoService;
        $this->errorRenderer = $errorRenderer;
    }
    /**
     * Remove slash and redirect 301.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeTrailingSlash(\Symfony\Component\HttpFoundation\Request $request)
    {
        $pathInfo = $request->getPathInfo();
        $requestUri = $request->getRequestUri();
        $url = str_replace($pathInfo, rtrim($pathInfo, ' /'), $requestUri);
        return $this->redirect($url, 301);
    }
    /**
     * Show Exception action.
     */
    public function showException(\Symfony\Component\HttpFoundation\Request $request, \Throwable $exception, \Symfony\Component\HttpKernel\Log\DebugLoggerInterface $logger = null)
    {
        if (!$exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $exception = $this->errorRenderer->render($exception);
            return new \Symfony\Component\HttpFoundation\Response($exception->getAsString(), $exception->getStatusCode(), $exception->getHeaders());
        }
        $host = $request->getHost();
        $site = $this->siteService->findOneByHost($host);
        if (!$site) {
            $site = $this->siteService->findOneToEdit($this->get('session')->get('siteId'));
            if (!$site) {
                $site = $this->siteService->findOneByRef('darkwood');
            }
        }
        $page = new \App\Entity\Page();
        $page->setRef('404');
        $page->setSite($site);
        $pageTranslation = new \App\Entity\PageTranslation();
        $pageTranslation->setTitle('404');
        $pageTranslation->setLocale($request->getLocale());
        $page->addTranslation($pageTranslation);
        $response = $this->render('common/pages/404.html.twig', ['page' => $pageTranslation, 'site_ref' => $site->getRef()]);
        $response->headers->set('X-Status-Code', 404);
        $response->setStatusCode(404);
        return $response;
    }
    public function seo($context)
    {
        return $this->render('common/partials/seo.html.twig', ['data' => $this->seoService->getSeo($context)]);
    }
    public function hreflangs(\Symfony\Component\HttpFoundation\Request $request, $ref, $entity)
    {
        $pageLinks = $this->pageService->getPageLinks($ref, $entity, $request->getHost(), $request->getLocale());
        return $this->render('common/partials/hreflangs.html.twig', ['pageLinks' => $pageLinks]);
    }
    /**
     * @param string $templateName
     * @param array $context
     * @param string $fromEmail
     * @param string $toEmail
     *
     * @throws \Throwable
     */
    private function sendMail($templateName, $context, $fromEmail, $toEmail): void
    {
        $template = $this->twig->load($templateName);
        $subject = $template->renderBlock('subject', $context);
        $textBody = $template->renderBlock('body_text', $context);
        $htmlBody = $template->renderBlock('body_html', $context);
        $message = (new \Symfony\Component\Mime\Email())->from($fromEmail)->to($toEmail)->subject($subject);
        if (!empty($htmlBody)) {
            $message->html($htmlBody)->text($textBody);
        } else {
            $message->html($textBody);
        }
        $this->mailer->send($message);
    }
    /**
     * @param string $ref
     *
     * @return PageTranslation
     */
    public function getPage(\Symfony\Component\HttpFoundation\Request $request, $ref)
    {
        $page = $this->pageService->findOneActiveByRefAndHost($ref, $request->getHost());
        if (!$page) {
            throw $this->createNotFoundException('Page not found !');
        }
        return $page->getOneTranslation($request->getLocale());
    }
    public function sitemap(\Symfony\Component\HttpFoundation\Request $request, $ref)
    {
        $page = $this->getPage($request, $ref);
        $siteRef = $page->getPage()->getSite()->getRef();
        $sitemap = $this->siteService->getSitemap($request->getLocale());
        return $this->render('common/pages/sitemap.html.twig', ['page' => $page, 'sitemap' => $sitemap, 'site_ref' => $siteRef]);
    }
    public function sitemapXml(\Symfony\Component\HttpFoundation\Request $request)
    {
        $sitemapXml = $this->siteService->getSitemapXml($request->getHost(), $request->getLocale());
        $response = new \Symfony\Component\HttpFoundation\Response($sitemapXml);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        return $response;
    }
    public function rss(\Symfony\Component\HttpFoundation\Request $request)
    {
        $rssXml = $this->siteService->getRssXml($request->getHost(), $request->getLocale());
        $response = new \Symfony\Component\HttpFoundation\Response($rssXml);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        return $response;
    }
    public function contact(\Symfony\Component\HttpFoundation\Request $request, $ref)
    {
        $page = $this->getPage($request, $ref);
        $siteRef = $page->getPage()->getSite()->getRef();
        $contact = new \App\Entity\Contact();
        $form = $this->createForm(\App\Form\ContactType::class, $contact);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $contact->setUser($this->getUser());
                $this->contactService->save($contact);
                $this->get('session')->getFlashBag()->add('success', $this->translator->trans('common.contact.submited'));
                try {
                    $this->sendMail('common/mails/contact.html.twig', ['contact' => $contact], $contact->getEmail(), 'contact@darkwood.fr');
                    $contact->setEmailSent(true);
                } catch (\Symfony\Component\Mailer\Exception\TransportException $exception) {
                    $contact->setEmailSent(false);
                }
                $this->contactService->save($contact);
                return $this->redirect($this->generateUrl($siteRef . '_contact', ['ref' => $ref]));
            }
        }
        return $this->render('common/pages/contact.html.twig', ['form' => $form->createView(), 'page' => $page, 'site_ref' => $siteRef]);
    }
}
