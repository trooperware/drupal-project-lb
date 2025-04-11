<?php
namespace Drupal\basic_config\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CourseContext {
  protected $session;

  public function __construct(SessionInterface $session) {
    $this->session = $session;
  }

  public function setCurrentCourse($nid) {
    $this->session->set('current_course', $nid);
  }

  public function getCurrentCourse() {
    return $this->session->get('current_course');
  }

  public function clearCurrentCourse() {
    $this->session->remove('current_course');
  }
}
