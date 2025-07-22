<?php

namespace Drupal\students\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Content access control event subscriber.
 */
class ContentAccessSubscriber implements EventSubscriberInterface {

  protected $messenger;
  protected $currentUser;

  public function __construct(MessengerInterface $messenger, AccountInterface $current_user) {
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['checkAccess', 28],
    ];
  }

  public function checkAccess(RequestEvent $event) {
    $request = $event->getRequest();
    $route_match = \Drupal::routeMatch();

    // Solo aplicar a rutas de nodos.
    if ($route_match->getRouteName() !== 'entity.node.canonical') {
      return;
    }

    $node = $route_match->getParameter('node');
    if (!$node instanceof Node) {
      return;
    }

    $type = $node->bundle();

    // Solo aplicar a topic o module.
    if (!in_array($type, ['topic', 'module'])) {
      return;
    }

    // Si es anónimo, redirigir y mostrar mensaje.
    if ($this->currentUser->isAnonymous()) {
      $login_url = Url::fromRoute('user.login')->toString();
      $reset_url = Url::fromRoute('user.pass')->toString();
      $register_url = Url::fromRoute('user.register')->toString();

      $message = new FormattableMarkup(
        'Si quieres acceder a los contenidos de este curso, solicita acceso rellenando el formulario de registro. Si ya dispones de acceso, identifícate en la <a href=":login">página de acceso</a>. Si has olvidado la contraseña, solicita un <a href=":reset">reseteo</a> en esta página.',
        [':login' => $login_url, ':reset' => $reset_url]
      );

      $this->messenger->addError($message);
      $event->setResponse(new RedirectResponse($register_url));
      return;
    }

    // Si es administrador, permitir acceso.
    if (in_array('administrator', $this->currentUser->getRoles())) {
      return;
    }

    // Buscar el curso padre.
    if ($type === 'module' && $node->hasField('field_course') && !$node->get('field_course')->isEmpty()) {
      $course_node = $node->get('field_course')->entity;
    }
    elseif ($type === 'topic' && $node->hasField('field_module') && !$node->get('field_module')->isEmpty()) {
      $module_node = $node->get('field_module')->entity;
      if ($module_node && $module_node->hasField('field_course') && !$module_node->get('field_course')->isEmpty()) {
        $course_node = $module_node->get('field_course')->entity;
      }
    }

    if (!isset($course_node)) {
      return;
    }

    // Comprobar si el usuario tiene el curso en su perfil.
    $user = \Drupal\user\Entity\User::load($this->currentUser->id());
    if ($user->hasField('field_courses')) {
      $user_courses = array_map(
        fn($item) => $item->entity->id(),
        $user->get('field_courses')->referencedEntities()
      );

      if (in_array($course_node->id(), $user_courses)) {
        return;
      }
    }

    // No tiene acceso.
    $message = new FormattableMarkup(
      'En estos momentos no tienes acceso al curso, ponte en contacto con <a href="mailto:info@contraste.info">nosotros</a> para solicitar acceso.',
      []
    );
    $this->messenger->addError($message);

    $event->setResponse(new RedirectResponse(Url::fromRoute('<front>')->toString()));
  }
}
