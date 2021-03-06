<?php

namespace App\Controller;

use App\Entity\CommentArticle;
use App\Form\CommentType;
use App\Services\ArticleService;
use App\Services\CommentService;
use App\Services\PageService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/', name: 'blog_', host: '%blog_host%', priority: -1)]
class BlogController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    public function __construct(private CommonController $commonController, private AuthenticationUtils $authenticationUtils, private TranslatorInterface $translator, private PaginatorInterface $paginator, private PageService $pageService, private ArticleService $articleService, private CommentService $commentService)
    {
    }

    public function menu(Request $request, $ref, $entity)
    {
        $lastUsername = $this->authenticationUtils->getLastUsername();
        $csrfToken    = $this->get('security.csrf.token_manager')->getToken('authenticate')->getValue();
        $pageLinks    = $this->pageService->getPageLinks($ref, $entity, $request->getHost(), $request->getLocale());

        return $this->render('blog/partials/menu.html.twig', ['last_username' => $lastUsername, 'csrf_token' => $csrfToken, 'pageLinks' => $pageLinks]);
    }

    #[Route(path: ['fr' => '/', 'en' => '/en', 'de' => '/de'], name: 'home', defaults: ['ref' => 'home'])]
    public function home(Request $request, $ref)
    {
        $page     = $this->commonController->getPage($request, $ref);
        $query    = $this->articleService->findActivesQueryBuilder($request->getLocale());
        $articles = $this->paginator->paginate($query, $request->query->get('page', 1), 10);

        return $this->render('blog/pages/home.html.twig', ['page' => $page, 'articles' => $articles, 'showLinks' => true]);
    }

    #[Route(path: ['fr' => '/plan-du-site', 'en' => '/en/sitemap', 'de' => '/de/sitemap'], name: 'sitemap', defaults: ['ref' => 'sitemap'])]
    public function sitemap(Request $request, $ref)
    {
        return $this->commonController->sitemap($request, $ref);
    }

    #[Route(path: ['fr' => '/sitemap.xml', 'en' => '/en/sitemap.xml', 'de' => '/de/sitemap.xml'], name: 'sitemap_xml')]
    public function sitemapXml(Request $request)
    {
        return $this->commonController->sitemapXml($request);
    }

    #[Route(path: ['fr' => '/rss', 'en' => '/en/rss', 'de' => '/de/rss'], name: 'rss')]
    public function rss(Request $request)
    {
        return $this->commonController->rss($request);
    }

    #[Route(path: ['fr' => '/contact', 'en' => '/en/contact', 'de' => '/de/kontakt'], name: 'contact', defaults: ['ref' => 'contact'])]
    public function contact(Request $request, $ref)
    {
        return $this->commonController->contact($request, $ref);
    }

    #[Route(path: ['fr' => '/article/{slug}', 'en' => '/en/article/{slug}', 'de' => '/de/article/{slug}'], name: 'article', defaults: ['ref' => 'article', 'slug' => null])]
    public function article(Request $request, $ref, $slug)
    {
        $page    = $this->commonController->getPage($request, $ref);
        $article = $this->articleService->findOneBySlug($slug, $request->getLocale());
        if (!$article) {
            throw $this->createNotFoundException('Article not found !');
        }
        $comment = new CommentArticle();
        $comment->setUser($this->getUser());
        $comment->setPage($page->getPage());
        $comment->setArticle($article);
        $form = $this->createForm(CommentType::class, $comment);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->commentService->save($comment);
                $this->get('session')->getFlashBag()->add('success', $this->translator->trans('common.comment.submited'));

                return $this->redirect($this->generateUrl('blog_article', ['slug' => $article->getOneTranslation($request->getLocale())->getSlug()]));
            }
        }
        $query    = $this->commentService->findActiveCommentByArticleQuery($article);
        $comments = $this->paginator->paginate($query, $request->query->get('page', 1), 10);

        return $this->render('blog/pages/article.html.twig', ['page' => $page, 'article' => $article, 'entity' => $article->getOneTranslation($request->getLocale()), 'showLinks' => true, 'form' => $form->createView(), 'comments' => $comments]);
    }
}
