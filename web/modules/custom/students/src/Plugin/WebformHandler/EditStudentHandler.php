<?php

namespace Drupal\students\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
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
      $user->save();

      $this->messenger()->addStatus($this->t("Cursos del estudiante con ID @uid actualizados correctamente.", ['@uid' => $uid]));
    }
    else {
      $this->messenger()->addError($this->t("No se pudo actualizar el usuario con ID @uid.", ['@uid' => $uid]));
    }
  }

}
