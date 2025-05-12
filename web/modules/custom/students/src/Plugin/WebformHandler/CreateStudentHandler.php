<?php

namespace Drupal\students\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\user\Entity\User;

/**
 * Webform handler para crear estudiantes.
 *
 * @WebformHandler(
 *   id = "create_student",
 *   label = @Translation("Create Student"),
 *   category = @Translation("Students"),
 *   description = @Translation("Crea un usuario con rol student y asigna cursos."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class CreateStudentHandler extends WebformHandlerBase {

  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();

    $name = $data['nombre'] ?? NULL;
    $email = $data['mail'] ?? NULL;
    $courses = $data['courses'] ?? [];

    $existing_users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);

    if (!empty($existing_users)) {
      $this->messenger()->addError("Ya existe un usuario con el correo '$email'.");
      $form_state->setErrorByName('mail', $this->t('Este correo ya está registrado.'));
      return;
    }

    $user = User::create([
      'name' => $name ?? 'student_' . time(),
      'mail' => $email,
      'status' => 1,
      'roles' => ['student'],
    ]);

    if (!empty($courses)) {
      $user->set('field_courses', $courses);
    }

    $user->save();

    \Drupal::service('user.mail')->send($user, 'register_admin_created', $email, \Drupal::languageManager()->getDefaultLanguage(), [
      'account' => $user,
      'password' => NULL,
    ]);

    $this->messenger()->addStatus("Usuario '$email' creado correctamente y asignado al rol student.");
  }

}
