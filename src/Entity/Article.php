<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Traits\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
/**
 * Article.
 *
 * @ORM\Table(name="article")
 * @ORM\Entity(repositoryClass="App\Repository\ArticleRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Article implements \Stringable
{
    use \App\Entity\Traits\TimestampTrait;
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * Translations.
     *
     * @ORM\OneToMany(targetEntity="App\Entity\ArticleTranslation", mappedBy="article", cascade={"persist", "remove"})
     */
    protected $translations;
    /**
     * @var ArrayCollection<Tag>
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\Tag", inversedBy="articles", cascade={"persist"})
     * @ORM\JoinTable(name="article_tag")
     */
    protected $tags;
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->tags = new \Doctrine\Common\Collections\ArrayCollection();
    }
    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * Add translations.
     */
    public function addTranslation(\App\Entity\ArticleTranslation $translations): void
    {
        $this->translations[] = $translations;
        $translations->setArticle($this);
    }
    /**
     * Remove translations.
     */
    public function removeTranslation(\App\Entity\ArticleTranslation $translations)
    {
        $this->translations->removeElement($translations);
        $translations->setArticle(null);
    }
    /**
     * Get translations.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTranslations()
    {
        return $this->translations;
    }
    /**
     * Get one translation.
     *
     * @param string $locale Locale
     *
     * @return ArticleTranslation
     */
    public function getOneTranslation($locale = null)
    {
        /** @var ArticleTranslation $translation */
        foreach ($this->getTranslations() as $translation) {
            if ($translation->getLocale() == $locale) {
                return $translation;
            }
        }
        return $this->getTranslations()->current();
    }
    public function __toString(): string
    {
        $articleTs = $this->getOneTranslation();
        return $articleTs ? $articleTs->getTitle() : '';
    }
    // KEYWORDS
    /**
     * Add tags.
     */
    public function addTag(\App\Entity\Tag $tag): void
    {
        $this->tags[] = $tag;
        $tag->addArticle($this);
    }
    /**
     * Remove tags.
     */
    public function removeTag(\App\Entity\Tag $tag)
    {
        $this->tags->removeElement($tag);
        $tag->removeArticle($this);
    }
    public function removeAllTags()
    {
        foreach ($this->getTags() as $tag) {
            $this->removeTag($tag);
        }
    }
    /**
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getTags()
    {
        return $this->tags;
    }
    public function getAllTagTitles()
    {
        $tagTitles = [];
        foreach ($this->tags as $tag) {
            $tagTitles[] = $tag->getOneTranslation()->getTitle();
        }
        return $tagTitles;
    }
}
