<?php

namespace App\Controller\Admin;

use App\Entity\App;
use App\Entity\PageTranslation;
use App\Form\Admin\PageTranslationType;
use App\Services\AppService;
use App\Services\PageService;
use App\Services\SiteService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
#[\Symfony\Component\Routing\Annotation\Route('/{_locale}/apps', name: 'admin_app_', host: '%admin_host%', requirements: ['_locale' => 'en|fr|de'])]
class AppController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    public function __construct(private \Symfony\Contracts\Translation\TranslatorInterface $translator, private \Knp\Component\Pager\PaginatorInterface $paginator, private \App\Services\AppService $appService, private \App\Services\PageService $pageService, private \App\Services\SiteService $siteService)
    {
        $this->translator = $translator;
        $this->paginator = $paginator;
        $this->appService = $appService;
        $this->pageService = $pageService;
        $this->siteService = $siteService;
    }
    #[Route('/list', name: 'list')]
    public function list(\Symfony\Component\HttpFoundation\Request $request)
    {
        $form = $this->createSearchForm();
        $form->handleRequest($request);
        $query = $this->appService->getQueryForSearch($form->getData(), 'app');
        $entities = $this->paginator->paginate($query, $request->query->get('page', 1), 20);
        return $this->render('admin/app/index.html.twig', ['entities' => $entities, 'search_form' => $form->createView()]);
    }
    private function createSearchForm()
    {
        $data = [];
        return $this->createFormBuilder($data)->setAction($this->generateUrl('admin_app_list'))->setMethod('GET')->add('id', \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['required' => false, 'label' => 'Id'])->add('submit', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, ['label' => 'Search'])->getForm();
    }
    private function manage(\Symfony\Component\HttpFoundation\Request $request, \App\Entity\PageTranslation $entityTranslation)
    {
        $mode = $entityTranslation->getId() ? 'edit' : 'create';
        $form = $this->createForm(\App\Form\Admin\PageTranslationType::class, $entityTranslation, ['locale' => $request->getLocale()]);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var App $app */
                $app = $entityTranslation->getPage();
                foreach ($app->getContents() as $content) {
                    $content->setLocale($request->getLocale());
                }
                $this->pageService->saveTranslation($entityTranslation, $form->get('export_locales')->getData() === true);
                // Launch the message flash
                $this->get('session')->getFlashBag()->add('notice', $this->translator->trans('notice.form.updated'));
                return $this->redirect($this->generateUrl('admin_app_edit', ['id' => $entityTranslation->getPage()->getId()]));
            }
            $this->get('session')->getFlashBag()->add('error', $this->translator->trans('notice.form.error'));
        }
        return $this->render('admin/app/' . $mode . '.html.twig', ['form' => $form->createView(), 'entity' => $entityTranslation, 'url' => $entityTranslation->getId() ? $this->pageService->getUrl($entityTranslation, true, true) : null]);
    }
    #[Route('/create', name: 'create')]
    public function create(\Symfony\Component\HttpFoundation\Request $request)
    {
        $entity = new \App\Entity\App();
        $entity->setCreated(new \DateTime());
        $entityTranslation = new \App\Entity\PageTranslation();
        $entityTranslation->setLocale($request->getLocale());
        $entity->addTranslation($entityTranslation);
        return $this->manage($request, $entityTranslation);
    }
    #[Route('/edit/{id}', name: 'edit')]
    public function edit(\Symfony\Component\HttpFoundation\Request $request, $id)
    {
        $entity = $this->appService->findOneToEdit($id, $request->getLocale());
        if (!$entity) {
            throw $this->createNotFoundException('App not found');
        }
        $entity->setUpdated(new \DateTime());
        $entityTranslation = $entity->getOneTranslation($request->getLocale());
        if (!$entityTranslation instanceof \App\Entity\PageTranslation || $entityTranslation->getLocale() != $request->getLocale()) {
            $entityTranslation = new \App\Entity\PageTranslation();
            $entityTranslation->setLocale($request->getLocale());
            $entity->addTranslation($entityTranslation);
        }
        $entityTranslation->setUpdated(new \DateTime());
        return $this->manage($request, $entityTranslation);
    }
    #[Route('/delete/{id}', name: 'delete')]
    public function delete(\Symfony\Component\HttpFoundation\Request $request, $id)
    {
        /** @var App $app */
        $app = $this->appService->findOneToEdit($id, $request->getLocale());
        if (!$app) {
            throw $this->createNotFoundException('App not found');
        }
        $this->appService->remove($app);
        // Launch the message flash
        $this->get('session')->getFlashBag()->add('notice', $this->translator->trans('notice.form.deleted'));
        return $this->redirect($request->headers->get('referer'));
    }
}
