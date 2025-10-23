<?php

namespace Drupal\students\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\user\Entity\User;

/**
 * Webform handler para editar estudiantes.
 *
 * @WebformHandler(
 *   id = "edit_student",
 *   label = @Translation("Edit Student"),
 *   category = @Translation("Students"),
 *   description = @Translation("Edita cursos asignados al estudiante."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class EditStudentHandler extends WebformHandlerBase {

  /**
   * Valida que el usuario exista y que el ID sea correcto.
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();
    $uid = $data['userid'] ?? \Drupal::request()->query->get('uid');

    if (!$uid || !is_numeric($uid)) {
      $form_state->setErrorByName('userid', $this->t('ID de usuario no válido o no proporcionado.'));
      return;
    }

    $user = User::load($uid);
    if (!$user) {
      $form_state->setErrorByName('userid', $this->t('No se encontró el usuario con ID @uid.', ['@uid' => $uid]));
    }

    if (empty($data['school']) && empty(trim((string)($data['school_new'] ?? '')))) {
      $form_state->setErrorByName('school', $this->t('Selecciona un colegio o escribe uno nuevo.'));
    }
  }

  /**
   * Asigna los cursos al usuario después de la validación.
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();
    $uid = $data['userid'];
    $courses = $data['courses'] ?? [];

    $user = User::load($uid);
    if ($user) {
      $user->set('field_courses', $courses);

      $VOCAB = 'school'; // machine name del vocabulario
      $school_value = $data['school'] ?? NULL;            // entity select → tid o array
      $school_new   = trim((string)($data['school_new'] ?? ''));
      $tid = NULL;

      // a) seleccionado existente
      if (!empty($school_value)) {
        $tid = is_array($school_value) ? (int) reset($school_value) : (int) $school_value;
      }

      // b) si no hay selección y hay texto → buscar o crear
      if ($school_new !== '') {
        $existing = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid', $VOCAB)
          ->condition('name', $school_new)
          ->accessCheck(TRUE)               // evita el QueryException
          ->range(0, 1)
          ->execute();

        if ($existing) {
          $tid = (int) reset($existing);
        }
        else {
          $term = Term::create(['vid' => $VOCAB, 'name' => $school_new]);
          $term->save();
          $tid = (int) $term->id();
        }
      }

      // c) asignar al usuario
      if ($tid) {
        $user->set('field_school', ['target_id' => $tid]);
      }

      $user->save();

      $this->messenger()->addStatus($this->t("Cursos del estudiante con ID @uid actualizados correctamente.", ['@uid' => $uid]));
    }
    else {
      $this->messenger()->addError($this->t("No se pudo actualizar el usuario con ID @uid.", ['@uid' => $uid]));
    }
  }

}
