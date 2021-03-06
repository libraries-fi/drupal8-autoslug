<?php

namespace Drupal\autoslug;

use SplPriorityQueue;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Path\AliasStorageInterface;

class AliasGenerator {
  protected $aliasStorage;
  protected $sluggers;

  public function __construct(AliasStorageInterface $alias_storage) {
    $this->aliasStorage = $alias_storage;
    $this->sluggers = new SplPriorityQueue;
  }

  public function addSlugger(SluggerInterface $slugger, $priority = 0) {
    $this->sluggers->insert($slugger, $priority);
  }

  public function fetchExistingAlias(EntityInterface $entity) {
    $langcode = $entity->language()->getId();
    $cache_key = '/' . $entity->urlInfo()->getInternalPath();
    $match = $this->aliasStorage->lookupPathAlias($cache_key, $langcode);
    return $match;
  }

  public function entityAliasExists(EntityInterface $entity) {
    return $this->fetchExistingAlias($entity) != NULL;
  }

  public function createAlias(EntityInterface $entity, $force = FALSE) {
    if (!$this->entityAliasExists($entity) || $force) {
      foreach ($this->sluggers as $slugger) {
        if ($slugger->applies($entity)) {
          $langcode = $entity->language()->getId();
          $alias = $slugger->build($entity);
          $alias = $this->ensureAliasUnique($alias, $langcode);
          $cache_key = '/' . $entity->urlInfo()->getInternalPath();
          $this->aliasStorage->save($cache_key, $alias, $langcode);
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  public function createAllAliases(EntityInterface $entity) {
    foreach ($entity->getTranslationLanguages() as $language) {
      $this->createAlias($entity->getTranslation($language->getId()));
    }
  }

  public function ensureAliasUnique($base, $langcode) {
    $alias = $base;
    for ($i = 1; $this->aliasStorage->lookupPathSource($alias, $langcode); $i++) {
      $alias = implode('-', [$base, $i]);
    }
    return $alias;
  }
}
