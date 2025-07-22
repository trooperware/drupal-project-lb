<?php

namespace Drupal\students\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Añade el curso al breadcrumb de los tipos topic y module.
 */
class CourseTopicBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    return $node && $node->getEntityTypeId() === 'node' && in_array($node->bundle(), ['topic', 'module']);
  }

  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute('Inicio', '<front>'));

    $node = $route_match->getParameter('node');
    if (!$node) {
      return $breadcrumb;
    }

    $target_field = $node->bundle() === 'topic' ? 'field_topic' : 'field_module';

    // Buscar todos los nodos course.
    $course_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'course']);

    foreach ($course_nodes as $course) {
      if (!$course->hasField('field_modules') || $course->get('field_modules')->isEmpty()) {
        continue;
      }

      foreach ($course->get('field_modules') as $paragraph_item) {
        $paragraph = $paragraph_item->entity;
        if (!$paragraph || !$paragraph->hasField($target_field)) {
          continue;
        }

        $referenced = $paragraph->get($target_field)->entity;
        if ($referenced && $referenced->id() === $node->id()) {
          $breadcrumb->addLink(Link::createFromRoute($course->label(), 'entity.node.canonical', ['node' => $course->id()]));
          break 2; // Salimos de los dos bucles, ya lo encontramos
        }
      }
    }

    // Añadir el nodo actual.
    $breadcrumb->addLink(Link::createFromRoute($node->label(), '<none>'));
    return $breadcrumb;
  }

}
