<?php

namespace App\Services;

use App\Entity\App;
use App\Entity\Article;
use App\Entity\PageTranslation;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * Class SeoService
 *
 * Object manager of site.
 */
class SeoService
{
    /**
     * @var CacheInterface
     */
    protected $appCache;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var UploaderHelper
     */
    protected $uploaderHelper;

    public function __construct(
        CacheInterface $appCache,
        RouterInterface $router,
        UploaderHelper $uploaderHelper
    ) {
        $this->appCache       = $appCache;
        $this->router         = $router;
        $this->uploaderHelper = $uploaderHelper;
    }

    public function getSeo($context, $force = false)
    {
        /** @var PageTranslation $pageTranslation */
        $pageTranslation = $context['page'];
        $cacheId = 'seo-' . $pageTranslation->getId();

        return $this->appCache->get($cacheId, function (ItemInterface $item) use ($context) {
            $item->expiresAfter(43200); // 12 hours

            /** @var PageTranslation $pageTranslation */
            $pageTranslation = $context['page'];
            $data = [
                'title'       => $pageTranslation->getTitle(),
                'description' => $pageTranslation->getDescription(),
                'keywords'    => $pageTranslation->getSeoKeywords(),
                'facebook' => [
                    'title'       => ($pageTranslation->getOgTitle() != '') ? $pageTranslation->getOgTitle() : $pageTranslation->getTitle(),
                    'description' => ($pageTranslation->getOgDescription() != '') ? $pageTranslation->getOgDescription() : $pageTranslation->getDescription(),
                    'type'        => ($pageTranslation->getOgType() ? $pageTranslation->getOgType() : 'article'),
                    'url'         => '',
                    'site_name'   => $pageTranslation->getPage()->getSite()->getName(),
                    'src'         => $this->uploaderHelper->asset($pageTranslation, 'ogImage'),
                ],
                'twitter'     => [
                    'card'        => ($pageTranslation->getTwitterCard() ? $pageTranslation->getTwitterCard() : 'summary'),
                    'title'       => ($pageTranslation->getTwitterTitle() != '') ? $pageTranslation->getTwitterTitle() : $pageTranslation->getTitle(),
                    'description' => ($pageTranslation->getTwitterDescription() != '') ? $pageTranslation->getTwitterDescription() : $pageTranslation->getDescription(),
                    'site'        => $pageTranslation->getTwitterSite(),
                    'src'         => $this->uploaderHelper->asset($pageTranslation, 'twitterImage'),
                ]
            ];

            $page = $pageTranslation->getPage();
            if($page instanceof App) {
                $data = array_replace_recursive($data, [
                    'facebook' => [
                        'src'         => $this->uploaderHelper->asset($page, 'banner'),
                    ],
                    'twitter'  => [
                        'src'         => $this->uploaderHelper->asset($page, 'banner'),
                    ]
                ]);
            }

            if(isset($context['article']) && $context['article'] instanceof Article) {
                $articleTranslation = $context['article']->getOneTranslation($pageTranslation->getLocale());
                $data = array_replace_recursive($data, [
                    'title'    => $articleTranslation->getTitle(),
                    'facebook' => [
                        'src'         => $this->uploaderHelper->asset($articleTranslation, 'image'),
                        'title'    => $articleTranslation->getTitle(),
                    ],
                    'twitter'  => [
                        'src'         => $this->uploaderHelper->asset($articleTranslation, 'image'),
                        'title'    => $articleTranslation->getTitle(),
                    ]
                ]);
            }

            return $data;
        }, $force ? INF : null);
    }
}
