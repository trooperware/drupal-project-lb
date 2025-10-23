<?php

namespace Drupal\students\Plugin\WebformHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
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

  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $submission) {
    parent::validateForm($form, $form_state, $submission);

    $data = $submission->getData();
    $school = $data['school'] ?? NULL;
    $school_new = trim((string)($data['school_new'] ?? ''));

    if (empty($school) && $school_new === '') {
      $form_state->setErrorByName('school', $this->t('Selecciona un colegio o escribe uno nuevo.'));
    }
  }

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

    // Ajusta al machine name real de tu vocabulario:
    $VOCAB = 'school'; 

    $data = $webform_submission->getData();
    $school_value = $data['school'] ?? NULL;      // entity select → tid o array
    $school_new   = trim((string)($data['school_new'] ?? ''));

    $tid = NULL;

    // 1) Si han elegido un término existente.
    if (!empty($school_value)) {
      // Si el elemento permite múltiple, toma el primero.
      if (is_array($school_value)) {
        $tid = (int) reset($school_value);
      } else {
        $tid = (int) $school_value;
      }
    }

    // 2) Si no hay selección pero han escrito uno nuevo → buscar o crear.
    if (!$tid && $school_new !== '') {
      // ¿Existe ya un término con ese nombre?
      $existing = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $VOCAB)
        ->condition('name', $school_new)
        ->accessCheck(TRUE)
        ->range(0, 1)
        ->execute();

      if ($existing) {
        $tid = (int) reset($existing);
      }
      else {
        // Crear término.
        $term = Term::create([
          'vid'  => $VOCAB,
          'name' => $school_new,
          // opcional: 'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
        ]);
        $term->save();
        $tid = (int) $term->id();
      }
    }

    // 3) Asignar al usuario si tenemos tid.
    if ($tid) {
      // Si field_school es multi, puedes pasar array de target_ids.
      $user->set('field_school', ['target_id' => $tid]);
    }

    $user->save();

    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'user';
    $key = 'register_admin_created'; // misma key que antes
    $to = $user->getEmail();
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

    $params = [
      'account' => $user,
      'password' => NULL,
    ];

    // Envía el correo.
    $mailManager->mail($module, $key, $to, $langcode, $params);

    $this->messenger()->addStatus("Usuario '$email' creado correctamente y asignado al rol student.");
  }

}
