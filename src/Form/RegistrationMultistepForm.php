<?php

namespace Drupal\registration_multistep_form\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Provides a form with two steps.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class RegistrationMultistepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'registration_multistep_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['wrapper-messages'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'messages-wrapper',
      ],
    ];

    // We want to deal with hierarchical form values.
    $form['#tree'] = TRUE;

    $form['step'] = [
      '#type' => 'value',
      '#value' => !empty($form_state->getValue('step')) ? $form_state->getValue('step') : 1,
    ];

    switch ($form['step']['#value']) {
      case 1:
        $limit_validation_errors = [['step']];

        $this->getFieldsStepOne($form, $form_state);
        break;

      case 2:
        $limit_validation_errors = [['step'], ['step1']];

        $this->getFieldsStepTwo($form, $form_state);
        break;

      default:
        $limit_validation_errors = [];
    }

    if ($form['step']['#value'] > 1) {
      $form['actions']['prev'] = [
        '#type' => 'submit',
        '#value' => $this->t('Check your personal information'),
        '#limit_validation_errors' => $limit_validation_errors,
        '#submit' => ['::prevSubmit'],
        '#ajax' => [
          'wrapper' => 'ajax-multistepform-wizard-wrapper',
          'callback' => [$this, 'loadStep'],
          'effect' => 'slide',
        ],
      ];
    }

    if ($form['step']['#value'] != 2) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Complete your location information'),
        '#submit' => ['::nextSubmit'],
        '#ajax' => [
          'wrapper' => 'ajax-multistepform-wizard-wrapper',
          'callback' => [$this, 'loadStep'],
          'effect' => 'slide',
        ],
      ];
    }

    if ($form['step']['#value'] == 2) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create you profile'),
      ];
    }

    $form['#prefix'] = '<div id="ajax-multistepform-wizard-wrapper">';
    $form['#suffix'] = '</div>';

    return $form;
  }

  /**
   * Ajax callback to load new step.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public function loadStep(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // We get all message and then delete them to avoid displaying again.
    $messages = $this->messenger()->all();
    $this->messenger()->deleteAll();

    // Update Form.
    $response->addCommand(new HtmlCommand('#ajax-multistepform-wizard-wrapper', $form));

    if (!empty($messages)) {
      // Form did not validate, get messages and render them.
      $messages = [
        '#theme' => 'status_messages',
        '#message_list' => $messages,
      ];

      $response->addCommand(new HtmlCommand('#messages-wrapper', $messages));
    }
    else {
      // Remove messages.
      $response->addCommand(new HtmlCommand('#messages-wrapper', ''));
    }

    return $response;
  }

  /**
   * Ajax callback that moves the form to the next step and rebuild the form.
   *
   * @param array $form
   *   The Form API form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   *
   * @return array
   *   The Form API form.
   */
  public function nextSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setValue('step', $form_state->getValue('step') + 1);
    $form_state->setRebuild();

    return $form;
  }

  /**
   * Ajax callback that moves the form to the previous step.
   *
   * @param array $form
   *   The Form API form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   *
   * @return array
   *   The Form API form.
   */
  public function prevSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setValue('step', $form_state->getValue('step') - 1);
    $form_state->setRebuild();

    return $form;
  }

  /**
   * Save away the current information.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->createUser($form_state);

    $this->messenger()->addMessage($this->t('Your profile has been created.'));
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  private function getFieldsStepOne(array &$form, FormStateInterface $form_state) {
    $form['step1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Personal information'),
    ];

    $form['step1']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#default_value' => $form_state->hasValue(['step1', 'first_name']) ? $form_state->getValue(['step1', 'first_name']) : '',
      '#required' => TRUE,
    ];

    $form['step1']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#default_value' => $form_state->hasValue(['step1', 'last_name']) ? $form_state->getValue(['step1', 'last_name']) : '',
      '#required' => TRUE,
    ];

    $form['step1']['gender'] = [
      '#type' => 'radios',
      '#options' => [
        'male' => $this->t('Male'),
        'female' => $this->t('Female'),
      ],
      '#title' => $this->t('Gender'),
      '#default_value' => $form_state->hasValue(['step1', 'gender']) ? $form_state->getValue(['step1', 'gender']) : 'male',
      '#required' => TRUE,
    ];

    $form['step1']['birthday'] = [
      '#type' => 'date',
      '#title' => $this->t('Birthday'),
      '#default_value' => $form_state->hasValue(['step1', 'birthday']) ? $form_state->getValue(['step1', 'birthday']) : '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  private function getFieldsStepTwo(array &$form, FormStateInterface $form_state) {
    $form['step1'] = [
      '#type' => 'value',
      '#value' => $form_state->getValue('step1'),
    ];

    $form['step2'] = [
      '#type' => 'fieldset',
      '#title' => t('Location information'),
    ];

    $form['step2']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#default_value' => $form_state->hasValue(['step2', 'city']) ? $form_state->getValue(['step2', 'city']) : '',
      '#required' => TRUE,
    ];

    $form['step2']['phone_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
      '#default_value' => $form_state->hasValue(['step2', 'phone_number']) ? $form_state->getValue(['step2', 'phone_number']) : '',
      '#required' => FALSE,
    ];

    $form['step2']['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address'),
      '#default_value' => $form_state->hasValue(['step2', 'address']) ? $form_state->getValue(['step2', 'address']) : '',
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  private function createUser(FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $username = $values['step1']['first_name'].$values['step1']['last_name'];

    $user = User::create();

    // Mandatory user creation settings
    $user->enforceIsNew();
    $user->setEmail("$username@site.com");
    $user->setUsername($username); // This username must be unique and accept only a-Z,0-9, - _ @ .

    // Fields
    $user->set('field_first_name', $values['step1']['first_name']);
    $user->set('field_last_name', $values['step1']['last_name']);
    $user->set('field_gender', $values['step1']['gender']);
    $user->set('field_birthday', $values['step1']['birthday']);
    $user->set('field_city', $values['step2']['city']);
    $user->set('field_phone_number', $values['step2']['phone_number']);
    $user->set('field_address', $values['step2']['address']);

    // Save user
    try {
      $user->save();
    } catch (EntityStorageException $e) {
      $this->messenger()->addMessage($e->getMessage());
    }
  }

}
