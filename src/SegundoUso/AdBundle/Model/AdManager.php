<?php

namespace SegundoUso\AdBundle\Model;

use Doctrine\Common\Persistence\ObjectManager;
use FOS\UserBundle\Model\UserInterface;
use SegundoUso\AdBundle\Util\RandomStringGeneratorInterface;
use SegundoUso\LocationBundle\Model\MunicipalityInterface;
use SegundoUso\LocationBundle\Model\MunicipalityManagerInterface;

class AdManager implements AdManagerInterface
{
    const AD_TOKEN_LENGTH = 16;
    const AD_PID_LENGTH = 64;

    /**
     * @var \SegundoUso\AdBundle\Util\RandomStringGeneratorInterface
     */
    protected $pidGenerator;

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    protected $objectManager;
    protected $class;
    protected $repository;

    public function __construct(RandomStringGeneratorInterface $pidGenerator, ObjectManager $om, $class)
    {
        $this->pidGenerator = $pidGenerator;

        $this->objectManager = $om;
        $this->repository = $this->objectManager->getRepository($class);

        $metadata = $om->getClassMetadata($class);
        $this->class = $metadata->getName();
    }

    /**
     * @return AdInterface
     */
    public function createAd()
    {
        $class = $this->class;
        $ad = new $class;

        return $ad;
    }

    public function updateAd(AdInterface $ad, $andFlush = true)
    {
        if (null === $ad->getPid()) $this->setPublicId($ad);
        if (null === $ad->getToken()) $this->setToken($ad);

        $this->objectManager->persist($ad);
        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    public function publishAd(AdInterface $ad)
    {
        $ad->setPublished(true);
        $this->updateAd($ad);
    }

    public function find($id)
    {
        return $this->repository->find($id);
    }

    public function findByPid($pid)
    {
        return $this->repository->findOneBy(array('pid' => $pid));
    }

    protected function setPublicId(AdInterface $ad)
    {
        $ad->setPid($this->pidGenerator->generate(self::AD_PID_LENGTH));
    }

    protected function setToken(AdInterface $ad)
    {
        $ad->setToken($this->pidGenerator->generate(self::AD_TOKEN_LENGTH));
    }

    public function findAllPublished()
    {
        return $this->repository->findBy(array('published' => true));
    }

    public function findByCategoryAndPublished(CategoryInterface $category)
    {
        return $this->repository->findBy(array(
            'published' => true,
            'category' => $category
        ));
    }

    public function findByMunicipalityAndPublished(MunicipalityInterface $municipality)
    {
        return $this->repository->findBy(array(
            'published' => true,
            'municipality' => $municipality
        ));
    }

    public function findByCategoryMunicipalityAndPublished(CategoryInterface $category, MunicipalityInterface $municipality)
    {
        return $this->repository->findBy(array(
            'published' => true,
            'category' => $category,
            'municipality' => $municipality
        ));
    }

    public function findFraudulent()
    {
        $ads = $this->repository->findAll();

        $fraudulentAds = array();
        foreach($ads as $ad) {
            if (!$ad->getMarks()->isEmpty()) $fraudulentAds[] = $ad;
        }

        return $fraudulentAds;
    }

    public function findFavoritesInCookie($favoritesIds)
    {
        if (null === $favoritesIds || empty($favoritesIds)) return array();

        return $this->repository->findPublishedByIds($favoritesIds);
    }

    public function findBySearchedTerm($tern, MunicipalityInterface $municipality)
    {
        return $this->repository->findBySearchedTerm($tern, $municipality);
    }

    public function findAdsForUser(UserInterface $user)
    {
        return $this->repository->findBy(array('user' => $user));
    }

    public function remove(AdInterface $ad, $andFlush = true, $hardDelete = false)
    {
        if ($hardDelete) {
            foreach ($this->objectManager->getEventManager()->getListeners() as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof \Gedmo\SoftDeleteable\SoftDeleteableListener) {
                        $this->objectManager->getEventManager()->removeEventListener($eventName, $listener);
                    }
                }
            }
        }
        $this->objectManager->remove($ad);
        if ($andFlush) {
            $this->objectManager->flush();
        }
    }
}