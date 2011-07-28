<?php

/**
 * List available reports
 */
function kprojectreports_admin_availablereports() {
  $reports = array(
    'kprojectreports_timespent' => 'Hours worked by project/user',
    'kprojectreports_billable'  => 'Hours billable by project',
    'kprojectreports_bankstatus' => 'Status of a project with a bank of hours',
  );

  return $reports;
}

/**
 * List already scheduled reports
 */
function kprojectreports_admin_listreports()  {
  global $base_path;

  $result = db_query("SELECT krid,title,frequency,report,mail FROM {kprojectreports_schedules} ORDER BY krid ASC");

  $header = array(
    'krid' => array('data' => 'ID', 'field' => 'krid'),
    'title' => array('data' => t('Title'), 'field' => 'title'),
    'frequency' => array('data' => t('Frequency'), 'field' => 'frequency'),
    'report' => array('data' => t('Report'), 'field' => 'report'),
    'mail' => array('data' => t('E-mail'), 'field' => 'mail'),
    'action' => array('data' => t('Actions'), 'field' => 'action'),
  );

  $items = array();

  while ($row = db_fetch_array($result)) {
    $img_edit   = '<img src="' . $base_path . drupal_get_path('module', 'kprojectreports') . '/images/edit.png" alt="' . t('edit') . '" />';
    $img_delete = '<img src="' . $base_path . drupal_get_path('module', 'kprojectreports') . '/images/delete.png" alt="' . t('delete') . '" />';
    $img_preview = '<img src="' . $base_path . drupal_get_path('module', 'kprojectreports') . '/images/preview.png" alt="' . t('preview') . '" />';

    $row['action']  = l($img_edit, 'admin/settings/kprojectreports/' . $row['krid'], array('html' => TRUE, 'attributes' => array('title' => t('edit'))));
    $row['action'] .= " ";
    $row['action'] .= l($img_delete, 'admin/settings/kprojectreports/' . $row['krid'] . '/delete', array('html' => TRUE, 'attributes' => array('title' => t('delete'))));
    $row['action'] .= " ";
    $row['action'] .= l($img_preview, 'admin/settings/kprojectreports/' . $row['krid'] . '/preview', array('html' => TRUE, 'attributes' => array('title' => t('preview'))));
    $items[] = $row;
  }

  if (count($items)) {
    $output .= theme('table', $header, $items);
  }

  $output .= "<p>" . l(t("Schedule a new report"), "admin/settings/kprojectreports/add") . "</p>";

  return $output;
}

/**
 * Form to schedule a new or existing report
 */
function kprojectreports_admin_editreport(&$form_state, $report = 'add')  {
  $form = array();
  $data = array();

  // Fetch previous values of the report (to set default values)
  if (is_numeric($report)) {
    $result = db_query("SELECT * FROM {kprojectreports_schedules} WHERE krid = %d", intval($report));
    $data = db_fetch_array($result);

    if ($data['options']) {
      $data['options'] = unserialize($data['options']);
    }
  }

  // Allow specific reports to add configuration elements to a report
  // (ex: start of financial year, pay day, quarter, etc.)
  if (! empty($form_state['values'])) {
    foreach ($form_state['values'] as $key => $value) {
      $form[$key] = array(
        '#type' => 'value',
        '#value' => $value,
      );
    }

    $form['step'] = array(
      '#type' => 'value',
      '#value' => 2,
    );

    // Frequency options (ex: first day of the week)
    include_once(drupal_get_path('module', 'kprojectreports') .'/kprojectreports_frequency.inc.php');
    $f = 'kprojectreports_frequency_editreport_' . $form_state['values']['frequency'];

    if (function_exists($f)) {
      $f($form_state, $form, $data);
    }

    // Other options from the report
    include_once(drupal_get_path('module', 'kprojectreports') .'/' . $form_state['values']['report'] . '.inc.php');
    $f = $form_state['values']['report'] . '_editreport_addtoform';

    if (function_exists($f)) {
      $f($form_state, $form, $data);
    }

    $form['#redirect'] = 'admin/settings/kprojectreports';

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#weight' => '100',
    );

    return $form;
  }

  $form['krid'] = array(
    '#type' => 'value',
    '#value' => $report,
  );

  $form['step'] = array(
    '#type' => 'value',
    '#value' => 1,
  );

  $form['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Title'),
    '#default_value' => $data['title'],
    '#required' => TRUE,
  );

  $form['frequency'] = array(
    '#type' => 'select',
    '#title' => t('Frequency'),
    '#default_value' => $data['frequency'],
    '#options' => array(
      'day' => 'Every day',
      'week' => 'Every week',
      'week2' => 'Every two weeks',
      'month' => 'Every month',
      'quarter' => 'Every quarter',
      'year' => 'Every year',
    ),
    '#required' => TRUE,
  );

  // TODO: this should use a hook to get a list of available reports
  $form['report'] = array(
    '#type' => 'select',
    '#title' => t('Report'),
    '#required' => TRUE,
    '#options' => kprojectreports_admin_availablereports(),
    '#default_value' => $data['report'],
  );

  $form['mail'] = array(
    '#type' => 'textfield',
    '#title' => 'Mail to',
    '#default_value' => $data['mail'],
    '#description' => 'E-mail address which will receive the report. You may specify multiples addresses by separating them with a comma.',
    '#required' => TRUE,
  );

  /* not used
  $form['format'] = array(
    '#type' => 'select',
    '#title' => t('Format'),
    '#default_value' => $data['format'],
    '#options' => array(
      'text' => 'Plain text',
      'html' => 'HTML',
      'csv'  => 'Comma separated values (csv)',
    ),
  );
  */

  $form['intro'] = array(
    '#type' => 'textarea',
    '#title' => t('Introduction text'),
    '#description' => t('The introduction text will be displayed above the report. Useful for when sending the report to clients.'),
    '#default_value' => $data['intro'],
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
  );

  $form['#redirect'] = 'admin/settings/kprojectreports';
  return $form;
}

function kprojectreports_admin_editreport_validate($form, &$form_state) {
  $mails = explode(',', $form_state['values']['mail']);

  foreach ($mails as $mail) {
    $mail = trim($mail);

    if (! valid_email_address($mail)) {
      form_set_error('mail', t("@mail is not a valid e-mail", array('@mail' => $mail)));
    }
  }
}

function kprojectreports_admin_editreport_submit($form, &$form_state) {
  if ($form_state['values']['step'] > 1) {
    $options = array();

    foreach ($form_state['values'] as $key => $val) {
      if (substr($key, 0, 7) == 'option_') {
        $options[substr($key, 7)] = $val;
      }
    }

    if (count($options)) {
      db_query("UPDATE {kprojectreports_schedules} 
                SET options = '%s'
                WHERE krid = %d",
                serialize($options), $form_state['values']['krid']);
    }

    drupal_set_message(t("Your report schedule has been updated."));
    return;
  }

  // Frequency options
  include_once(drupal_get_path('module', 'kprojectreports') .'/kprojectreports_frequency.inc.php');
  $f = 'kprojectreports_frequency_editreport_' . $form_state['values']['frequency'];

  if (function_exists($f)) {
    // rebuild for step 2
    $form_state['rebuild'] = TRUE;
  }

  // Check if there are options from the report
  // If yes, tell form api to rebuild the form so that we can configure them
  include_once(drupal_get_path('module', 'kprojectreports') .'/' . $form_state['values']['report'] . '.inc.php');
  $f = $form_state['values']['report'] . '_editreport_addtoform';

  if (function_exists($f)) {
    $form_state['rebuild'] = TRUE;
  }

  $krid      = $form_state['values']['krid'];
  $title     = filter_xss($form_state['values']['title']);
  $frequency = filter_xss($form_state['values']['frequency']);
  $report    = filter_xss($form_state['values']['report']);
  $mail      = filter_xss($form_state['values']['mail']);
  $format    = filter_xss($form_state['values']['format']);
  $intro     = filter_xss($form_state['values']['intro']);

  if ($krid == 'add') {
    db_query("INSERT INTO {kprojectreports_schedules} (title, frequency, report, mail, format, intro)
              VALUES ('%s', '%s', '%s', '%s', '%s', '%s')",
              $title, $frequency, $report, $mail, $format, $intro);

    // Store the ID of the newly created report so that the next step ("edit more options, if any")
    // can save its preferences.

    $form_state['values']['krid'] = db_last_insert_id("kprojectreports_schedules", 'krid');

    if ($form_state['rebuild']) {
      drupal_set_message(t("The following options specific to this reports must be filled in."));
    } else {
      drupal_set_message(t("Your report schedule has been added."));
    }
  } else {
    db_query("UPDATE {kprojectreports_schedules} 
              SET title = '%s',
                  frequency = '%s', 
                  report = '%s',
                  mail = '%s',
                  format = '%s',
                  intro = '%s'
              WHERE krid = %d",
              $title, $frequency, $report, $mail, $format, $intro, $krid);

    if (! $form_state['rebuild']) {
      drupal_set_message(t("Your report schedule has been updated."));
    }
  }
}

function kprojectreports_delete_form($form_state, $report = NULL, $params = NULL) {
  $form['krid'] = array(
    '#type' => 'value',
    '#value' => $report->krid,
  );

  return confirm_form($form,
    t('Are you sure you want to delete the report %reportname (#%krid)?', array('%reportname' => $report->title, '%krid' => $report->krid)),
    isset($_GET['destination']) ? $_GET['destination'] : 'admin/settings/kprojectreports',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

function kprojectreports_delete_form_submit($form, &$form_state) {
  kprojectreports_report_delete($form_state['values']['krid']);
  $form_state['redirect'] = 'admin/settings/kprojectreports';
}

function kprojectreports_report_delete($krid) {
  db_query('DELETE FROM {kprojectreports_schedules} WHERE krid = %d', $krid);
} 

/**
 * Preview a report on screen
 */
function kprojectreports_preview_form($form_state, $report = NULL, $params = NULL) {
  $daterun = date('Y-m-d', time());
  $reporttitle = $report->title;


  if ($_REQUEST['daterun']) {
    $daterun = check_plain($_REQUEST['daterun']);
  }

  // cheap token replacement in case there is [site-date] in the title
  $reporttitle = preg_replace('/\[site-date\]/', $daterun, $reporttitle);

  $form['krid'] = array(
    '#type' => 'value',
    '#value' => $report->krid,
  );

  $form['daterun'] = array(
    '#type' => 'date_popup',
    '#title' => t('Report run date'),
    '#date_format' => 'Y-m-d',
    '#default_value' => $daterun,
    '#description' => t('For example: if the date is 2012-02-20 and it is a monthly report, then report will be from 2012-01-01 to 2012-01-31. If you want to generate a partial monthly report for 2012-02, then enter 2012-03-01 as the run date.'),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
  );

  $form['reportdata'] = array(
    '#type' => 'fieldset',
    '#title' => $reporttitle,
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  // Run the report, mostly copied from kprojectreports_cron, refactor?
  include_once(drupal_get_path('module', 'kprojectreports') .'/kprojectreports_frequency.inc.php');
  $f = 'kprojectreports_frequency_timetorun_' . $report->frequency;

  if (function_exists($f)) {
    $daterun = strtotime(check_plain($_REQUEST['daterun']));
    list($timetorun, $date_start, $date_end) = $f($report, $daterun);
  }

  $reportfunc = $report->report; // ex: kprojectreports_timespent
  include_once(drupal_get_path('module', 'kprojectreports') .'/' . $report->report . '.inc.php');
  $report->options = unserialize($report->options);
  $output = $reportfunc($date_start, $date_end, $report);

  $form['reportdata']['intro'] = array(
    '#type' => 'markup',
    '#value' => '<p>' . $report->intro . '</p>',
  );

  $form['reportdata']['output'] = array(
    '#type' => 'markup',
    '#value' => $output,
  );

  return $form;
}


function kprojectreports_preview_form_submit($form, &$form_state) {
  $reportid = $form_state['values']['krid'];
  $daterun = check_plain($form_state['values']['daterun']);
  $daterun = substr($daterun, 0, 10); // grab only the date part, not time

  drupal_goto('admin/settings/kprojectreports/' . $reportid . '/preview', 'daterun=' . $daterun);
}

