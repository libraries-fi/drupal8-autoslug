<?php

namespace Drupal\autoslug\Slugger;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\autoslug\SluggerInterface;
use Drupal\autoslug\Slugger;

/**
 * Generates URL aliases based on autoslug_rule entities.
 */
class DefaultSlugger implements SluggerInterface {
  protected $entityManager;

  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  public function applies(EntityInterface $entity) {
    return $this->findApplicableRule($entity) != NULL;
  }

  /**
   * Extracts field values from the entity and creates an alias based on a pattern.
   *
   * Variables are notated by '{field_key}'.
   * Fields of child objects can be referenced also: '{object_field:child_key}'
   *
   * It is also possible to extract a substring by {field_key[0]} or {field_key[0:3]},
   * where the first integer is the first character and second integer length of the substring.
   *
   * @return string
   */
  public function build(EntityInterface $entity) {
    $rule = $this->findApplicableRule($entity);
    $tokens = $this->extractTokens($entity, $rule->getPattern(), $rule->getWordLimit());
    $alias = $this->processPattern($rule->getPattern(), $tokens);
    return $alias;
  }

  /**
   * Extract needed values from the entity.
   *
   * @param $entity Processed entity.
   * @param $pattern Base URL for the URL alias.
   * @param $max_words Limit word length of a single parameter to at most n words.
   * @return array
   */
  protected function extractTokens(EntityInterface $entity, $pattern, $max_words = 0) {
    preg_match_all('/\{(([\w|:]+)(?:\[(\d+)\]|\[(\d+):(\d+)\])?)\}/', $pattern, $matches, PREG_SET_ORDER);
    $tokens = [];

    foreach ($matches as $match) {
      // Filter empty strings in order to have substring parameters always in indices 3 and 4.
      $match = array_values(array_filter($match, 'strlen'));
      $key = $match[2];

      if (strpos($key, ':')) {
        list($child, $key) = explode(':', $key);
        $value = $entity->get($child)->entity->get($key)->value;
      } else {
        $value = $entity->get($key)->value;
      }

      if (isset($match[3])) {
        $pos = $match[3];
        $length = empty($match[4]) ? 1 : $match[4];
        $value = mb_substr($value, $pos, $length);
      }

      $tokens[$match[1]] = Slugger::slugify($value, FALSE, $max_words);
    }

    return $tokens;
  }

  /**
   * Replace tokens from the URL pattern.
   */
  protected function processPattern($pattern, array $tokens) {
    $keys = array_map(function($t) { return sprintf('{%s}', $t); }, array_keys($tokens));
    $alias = str_replace($keys, array_values($tokens), $pattern);
    $alias = Unicode::truncate($alias, 128, TRUE);
    return $alias;
  }

  protected function findApplicableRule(EntityInterface $entity) {
    return $this->entityManager->getStorage('autoslug_rule')->findApplicableRule($entity);
  }
}
