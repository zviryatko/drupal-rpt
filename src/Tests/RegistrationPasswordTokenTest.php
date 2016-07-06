<?php

namespace Drupal\rpt\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the rpt module.
 *
 * @group rpt
 */
class RegistrationPasswordTokenTest extends WebTestBase {

  /**
   * Disabled config schema checking temporarily until all errors are resolved.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['rpt', 'user'];

  /**
   * Sets up a Drupal site for running functional and integration tests.
   *
   * Don't require email verification and allow registration by site visitors
   * without administrator approval.
   */
  public function setUp() {
    parent::setUp();
    $config = $this->config('user.settings');
    $config
      ->set('verify_mail', TRUE)
      ->set('register', USER_REGISTER_VISITORS)
      ->save();
  }

  /**
   * Test password token in user registration email.
   */
  public function testUserRegistrationPasswordToken() {
    // Register new user.
    $edit = [];
    $edit['name'] = $name = $this->randomMachineName();
    $edit['mail'] = $mail = $edit['name'] . '@example.com';

    $this->drupalPostForm('user/register', $edit, t('Create new account'));
    /** @var \Drupal\user\UserStorageInterface $user_storage */
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user_storage->resetCache();
    $accounts = $user_storage->loadByProperties(['name' => $name, 'mail' => $mail]);
    $new_user = reset($accounts);
    $this->assertText(t('A welcome message with further instructions has been sent to your email address.'), 'User registered successfully.');

    // Check email for token.
    $captured_emails = $this->drupalGetMails();
    $this->assertEqual(count($captured_emails), 1, 'One email was captured.', 'Email');
    $captured_email = end($captured_emails);
    preg_match("/^password:(.*)$/m", $captured_email['body'], $matches);
    $pass = $matches[1];

    // Try to log in with received password.
    $auth = array(
      'name' => $name,
      'pass' => $pass,
    );
    $this->drupalPostForm('user/login', $auth, t('Log in'));
  }

  /**
   * Test password token in user registration email.
   */
  public function testAdminCreateAccountPasswordToken() {
    $user = $this->drupalCreateUser(array('administer users'));
    $this->drupalLogin($user);

    // Create new user and notify.
    $name = $this->randomMachineName();
    $edit = array(
      'name' => $name,
      'mail' => $name . '@example.com',
      'notify' => TRUE,
    );
    $this->drupalPostForm('admin/people/create', $edit, t('Create new account'));
    $this->assertText(t('A welcome message with further instructions has been emailed to the new user @name.', array('@name' => $edit['name'])));
    $this->drupalLogout();

    // Check email for token.
    $captured_emails = $this->drupalGetMails();
    $this->assertEqual(count($captured_emails), 1, 'One email was captured.', 'Email');
    $captured_email = end($captured_emails);
    preg_match("/^password:(.*)$/m", $captured_email['body'], $matches);
    $pass = $matches[1];

    // Try to log in with received password.
    $auth = array(
      'name' => $name,
      'pass' => $pass,
    );
    $this->drupalPostForm('user/login', $auth, t('Log in'));
  }

}
