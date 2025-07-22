<?php
namespace Drupal\students\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

class ContentAccessSubscriber implements EventSubscriberInterface {

  protected $messenger;
  protected $currentUser;

  public function __construct(MessengerInterface $messenger, $current_user) {
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['checkAccess', 29],
    ];
  }

  public function checkAccess(RequestEvent $event) {
    xdebug_break();
    \Drupal::logger('students')->notice('➡️ Ha entrat al ContentAccessSubscriber::checkAccess()');

    $request = $event->getRequest();
    $node = $request->attributes->get('node');

    if (!$node instanceof NodeInterface) {
      return;
    }

    if (!in_array($node->bundle(), ['module', 'topic'])) {
      return;
    }

    if ($this->currentUser->hasPermission('administer nodes')) {
      return;
    }

    if ($this->currentUser->isAnonymous()) {
      $login_url = Url::fromRoute('user.login')->toString();
      $reset_url = Url::fromRoute('user.pass')->toString();

      $message = new FormattableMarkup('Si quieres acceder a los contenidos de este curso, solicita acceso rellenando el formulario de registro. Si ya dispones de acceso, identifícate en la <a href=":login">página de acceso</a>. Si has olvidado la contraseña, solicita un <a href=":reset">reseteo</a> en esta página.', [
        ':login' => $login_url,
        ':reset' => $reset_url,
      ]);
$this->messenger->addError($message);

      $event->setResponse(new RedirectResponse(Url::fromRoute('user.register')->toString()));
      return;
    }

    $user = \Drupal\user\Entity\User::load($this->currentUser->id());
    $allowed_courses = $user->get('field_courses')->referencedEntities();
    $course_node = NULL;

    // Recorrer todos los cursos y buscar si este nodo pertenece a alguno
    $courses = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'course']);
    foreach ($courses as $course) {
      if (!$course->hasField('field_modules')) {
        continue;
      }
      foreach ($course->get('field_modules') as $module_item) {
        $paragraph = $module_item->entity;

        if (!$paragraph) {
          continue;
        }

        // Si es topic
        if ($node->bundle() === 'topic' && $paragraph->hasField('field_topic')) {
          $topic = $paragraph->get('field_topic')->entity;
          if ($topic && $topic->id() == $node->id()) {
            $course_node = $course;
            break 2;
          }
        }

        // Si es module
        if ($node->bundle() === 'module' && $paragraph->hasField('field_module')) {
          foreach ($paragraph->get('field_module') as $mod_ref) {
            $mod = $mod_ref->entity;
            if ($mod && $mod->id() == $node->id()) {
              $course_node = $course;
              break 2;
            }
          }
        }
      }
    }

    // Comprovar accés
    if ($course_node && !in_array($course_node->id(), array_map(fn($c) => $c->id(), $allowed_courses))) {
      $this->messenger->addError(t('En estos momentos no tienes acceso al curso, ponte en contacto con <a href="mailto:info@contraste.info">nosotros</a> para solicitar acceso.'));
      $event->setResponse(new RedirectResponse(Url::fromRoute('<front>')->toString()));
    }
  }
}
