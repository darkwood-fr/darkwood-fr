<?php

namespace App\DataFixtures;

use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\Site;
use App\Services\SiteService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
class PageFixtures extends \Doctrine\Bundle\FixturesBundle\Fixture implements \Doctrine\Common\DataFixtures\DependentFixtureInterface
{
    public function __construct(
        /**
         * @var ParameterBagInterface
         */
        protected \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBagInterface,
        /**
         * @var SiteService
         */
        private \App\Services\SiteService $siteService
    )
    {
    }
    public function load(\Doctrine\Persistence\ObjectManager $manager)
    {
        $this->createPage(['ref' => 'home', 'title' => 'Home'], $manager);
        $this->createPage(['ref' => 'contact', 'title' => 'Contact'], $manager);
        $this->createPage(['ref' => 'sitemap', 'title' => 'Sitemap'], $manager);
        $manager->flush();
    }
    public function createPage($params, \Doctrine\Persistence\ObjectManager $manager)
    {
        $sites = $this->siteService->findAll();
        foreach ($sites as $site) {
            $page = new \App\Entity\Page();
            $page->setRef($params['ref']);
            $page->setSite($site);
            foreach ($this->parameterBagInterface->get('app_locales') as $locale) {
                $pageTranslation = new \App\Entity\PageTranslation();
                $pageTranslation->setTitle($params['title']);
                $pageTranslation->setLocale($locale);
                $page->addTranslation($pageTranslation);
                $manager->persist($pageTranslation);
            }
            $manager->persist($page);
        }
        return $page;
    }
    public function getDependencies()
    {
        return [\App\DataFixtures\SiteFixtures::class];
    }
}
