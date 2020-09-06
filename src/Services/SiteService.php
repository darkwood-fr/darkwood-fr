<?php

namespace App\Services;

use App\Entity\Site;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Class SiteService
 *
 * Object manager of site.
 */
class SiteService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @var PageService
     */
    protected $pageService;

    /**
     * @var ArticleService
     */
    protected $articleService;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var Environment
     */
    protected $templating;

    public function __construct(
        EntityManagerInterface $em,
        PageService $pageService,
        ArticleService $articleService,
        TranslatorInterface $translator,
        Environment $templating
    ) {
        $this->em             = $em;
        $this->siteRepository = $em->getRepository(Site::class);
        $this->pageService    = $pageService;
        $this->articleService = $articleService;
        $this->translator     = $translator;
        $this->templating     = $templating;
    }

    /**
     * Update a site.
     *
     * @return Site
     */
    public function save(Site $site)
    {
        $site->setUpdated(new \DateTime('now'));
        $this->em->persist($site);
        $this->em->flush();

        return $site;
    }

    /**
     * Remove one site.
     */
    public function remove(Site $site)
    {
        $this->em->remove($site);
        $this->em->flush();
    }

    public function searchQuery($filters = [])
    {
        return $this->siteRepository->createQueryBuilder('s');
    }

    /**
     * Search.
     *
     * @param array $filters
     *
     * @return mixed
     */
    public function getQueryForSearch($filters = [], $state = null)
    {
        return $this->siteRepository->queryForSearch($filters, $state);
    }

    /**
     * Find one to edit.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function findOneToEdit($id)
    {
        return $this->siteRepository->findOneToEdit($id);
    }

    /**
     * Find one by host.
     *
     * @param $host
     *
     * @return Site|null
     */
    public function findOneByHost($host)
    {
        return $this->siteRepository->findOneByHost($host);
    }

    /**
     * Find all.
     *
     * @return mixed
     */
    public function findAll()
    {
        return $this->siteRepository->findAll();
    }

    /**
     * @return Site[]
     */
    public function findActives()
    {
        return $this->siteRepository->findActives();
    }

    public function getSitemap($locale)
    {
        $sites = $this->findActives();

        $sitemap = [];
        foreach ($sites as $site) {
            $ref  = $site->getRef();
            $host = $site->getHost();

            if (in_array($ref, ['me', 'blog'])) {
                continue;
            }

            $sitemap[$ref] = [
                'item'     => ['host' => $host, 'ref' => 'home', 'label' => 'common.sitemap.site_' . $ref],
                'children' => [
                    [
                        'item'     => ['label' => 'common.sitemap.login'],
                        'children' => [
                            ['item' => ['host' => $host, 'ref' => 'register']],
                            ['item' => ['host' => $host, 'ref' => 'profile']],
                        ],
                    ],
                ],
            ];

            if ($ref == 'darkwood') {
                $sitemap[$ref]['children'][] = [
                    'item'     => ['label' => 'common.sitemap.player'],
                    'children' => [
                        ['item' => ['host' => $host, 'ref' => 'play']],
                        ['item' => ['host' => $host, 'ref' => 'chat']],
                        ['item' => ['host' => $host, 'ref' => 'users']],
                        ['item' => ['host' => $host, 'ref' => 'rules']],
                        ['item' => ['host' => $host, 'ref' => 'guestbook']],
                        ['item' => ['host' => $host, 'ref' => 'extra']],
                    ],
                ];
                $sitemap[$ref]['children'][] = [
                    'item'     => ['label' => 'common.sitemap.rank'],
                    'children' => [
                        ['item' => ['host' => $host, 'ref' => 'rank', 'label' => 'darkwood.menu.rank_general']],
                        ['item' => ['host' => $host, 'ref' => 'rank', 'label' => 'darkwood.menu.rank_by_class']],
                        ['item' => ['host' => $host, 'ref' => 'rank', 'label' => 'darkwood.menu.rank_daily_fight']],
                    ],
                ];
            } elseif ($ref == 'apps') {
                $children = [];
                $apps     = $this->pageService->findActives($locale, 'app');
                foreach ($apps as $app) {
                    $children[] = ['item' => ['host' => $host, 'ref' => $app->getRef()]];
                }

                $sitemap[$ref]['children'][] = [
                    'item'     => ['label' => 'common.sitemap.apps'],
                    'children' => $children,
                ];
            } elseif ($ref == 'photos') {
                $sitemap[$ref]['children'][] = [
                    'item'     => ['label' => 'common.sitemap.gallery'],
                    'children' => [
                        ['item' => ['host' => $host, 'ref' => 'show']],
                        ['item' => ['host' => $host, 'ref' => 'demo']],
                        ['item' => ['host' => $host, 'ref' => 'help']],
                    ],
                ];
            }
        }

        $formatSitemap = function ($items) use (&$formatSitemap, $locale) {
            foreach ($items as $key => $child) {
                if (!isset($child['children'])) {
                    $child['children'] = [];
                }
                $child['label'] = null;
                $child['link']  = null;
                if (isset($child['item']['host'], $child['item']['ref'])) {
                    $page = $this->pageService
                        ->findOneActiveByRefAndHost($child['item']['ref'], $child['item']['host']);
                    if ($page) {
                        $pageTranslation = $page->getOneTranslation($locale);
                        if ($pageTranslation) {
                            $child['label'] = $pageTranslation->getTitle();
                            $child['link']  = $this->pageService->getUrl($pageTranslation, true);
                        }
                    }
                }
                if (isset($child['item']['label'])) {
                    $child['label'] = $this->translator->trans($child['item']['label']);
                }

                $child['children'] = $formatSitemap($child['children']);

                $items[$key] = $child;
            }

            return $items;
        };

        return $formatSitemap($sitemap);
    }

    public function getSitemapXml($host, $locale)
    {
        $pages = $this->pageService->findActives($locale, null, $host);

        $urls = [];
        foreach ($pages as $page) {
            $pageTranslation = $page->getOneTranslation();

            $urls[] = [
                'loc'  => $this->pageService->getUrl($pageTranslation),
                'date' => $pageTranslation->getUpdated(),
            ];
        }

        return $this->templating->render('common/partials/sitemapXml.html.twig', [
            'urls' => $urls,
        ]);
    }

    public function getFeed($host, $locale)
    {
        $feed = [];

        $articles = $this->articleService->findActives($locale);
        foreach ($articles as $article) {
            $feed[] = [
                'type' => 'article',
                'date' => $article->getCreated(),
                'item' => $article
            ];
        }

        usort($feed, function ($item1, $item2) {
            return $item1['date'] < $item2['date'];
        });

        return $feed;
    }

    public function getRssXml($host, $locale)
    {
        $feed = $this->getFeed($host, $locale);

        return $this->templating->render('common/partials/rssXml.html.twig', [
            'feed'   => $feed,
            'locale' => $locale,
            'host'   => $host,
        ]);
    }
}
