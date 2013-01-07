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

  $output = '';
  $result = db_query("SELECT krid, title, frequency, report, mail FROM {kprojectreports_schedules} ORDER BY krid ASC");

  $header = array(
    'krid' => array('data' => 'ID', 'field' => 'krid'),
    'title' => array('data' => t('Title'), 'field' => 'title'),
    'frequency' => array('data' => t('Frequency'), 'field' => 'frequency'),
    'report' => array('data' => t('Report'), 'field' => 'report'),
    'mail' => array('data' => t('E-mail'), 'field' => 'mail'),
    'action' => array('data' => t('Actions'), 'field' => 'action', ),
  );

  $items = array();

  foreach ($result as $record) {
    $img_edit   = '<img src="' . $base_path . drupal_get_path('module', 'kprojectreports') . '/images/edit.png" alt="' . t('edit') . '" />';
    $img_delete = '<img src="' . $base_path . drupal_get_path('module', 'kprojectreports') . '/images/delete.png" alt="' . t('delete') . '" />';
    $img_preview = '<img src="' . $base_path . drupal_get_path('module', 'kprojectreports') . '/images/preview.png" alt="' . t('preview') . '" />';

    $record->action  = l($img_edit, 'kprojectreports/' . $record->krid . '/edit', array('html' => TRUE, 'attributes' => array('title' => t('edit'))));
    $record->action .= " ";
    $record->action .= l($img_delete, 'kprojectreports/' . $record->krid . '/delete', array('html' => TRUE, 'attributes' => array('title' => t('delete'))));
    $record->action .= " ";
    $record->action .= l($img_preview, 'kprojectreports/' . $record->krid . '/preview', array('html' => TRUE, 'attributes' => array('title' => t('preview'))));
    $items[] = (array) $record;
  }

  if (count($items)) {
    $variables = array(
      'header' => $header,
      'rows' => $items,
      'sticky' => 1,
    );

    $output .= theme('table', $variables);
  }

  $output .= "<p>" . l(t("Schedule a new report"), "kprojectreports/add") . "</p>";

  return $output;
}

/**
 * Form to schedule a new or existing report
 */
function kprojectreports_admin_editreport($form, &$form_state, $report = 'add')  {
  $form = array();

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
      $f($form_state, $form, $report);
    }

    // Other options from the report
    include_once(drupal_get_path('module', 'kprojectreports') .'/' . $form_state['values']['report'] . '.inc.php');
    $f = $form_state['values']['report'] . '_editreport_addtoform';

    if (function_exists($f)) {
      $f($form_state, $form, $report);
    }

    $form['#redirect'] = 'kprojectreports';

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#weight' => '100',
    );

    return $form;
  }

  $form['krid'] = array(
    '#type' => 'value',
    '#value' => (is_object($report) ? $report->krid : 'add'),
  );

  $form['step'] = array(
    '#type' => 'value',
    '#value' => 1,
  );

  $form['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Title'),
    '#default_value' => (empty($report->title) ? '' : $report->title),
    '#required' => TRUE,
  );

  $form['frequency'] = array(
    '#type' => 'select',
    '#title' => t('Frequency'),
    '#default_value' => (empty($report->frequency) ? '' : $report->frequency),
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
    '#default_value' => (empty($report->report) ? '' : $report->report),
  );

  $form['mail'] = array(
    '#type' => 'textfield',
    '#title' => 'Mail to',
    '#default_value' => (empty($report->mail) ? '' : $report->mail),
    '#description' => 'E-mail address which will receive the report. You may specify multiples addresses by separating them with a comma.',
    '#required' => TRUE,
  );

  $form['intro'] = array(
    '#type' => 'textarea',
    '#title' => t('Introduction text'),
    '#description' => t('The introduction text will be displayed above the report. Useful for when sending the report to clients.'),
    '#default_value' => (empty($report->intro) ? '' : $report->intro),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
  );

  $form['#redirect'] = 'kprojectreports';
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
                SET options = :options
                WHERE krid = :krid",
                array(':options' => serialize($options), ':krid' => $form_state['values']['krid']));
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
    $insert = db_insert('kprojectreports_schedules');
    $insert->fields(array(
      'title' => $title,
      'frequency' => $frequency,
      'report' => $report,
      'mail' => $mail,
      'format' => $format,
      'intro' => $intro,
    ));

    // Store the ID of the newly created report so that the next step ("edit more options, if any")
    // can save its preferences.
    $form_state['values']['krid'] = $insert->execute();

    if ($form_state['rebuild']) {
      drupal_set_message(t("The following options specific to this reports must be filled in."));
    } else {
      drupal_set_message(t("Your report schedule has been added."));
    }
  } else {
    db_query("UPDATE {kprojectreports_schedules} 
              SET title = :title,
                  frequency = :frequency,
                  report = :report,
                  mail = :mail,
                  format = :format,
                  intro = :intro,
              WHERE krid = :krid",
              array(':title' => $title, ':frequency' => $frequency, ':report' => $report, ':mail' => $mail, ':format' => $format, ':intro' => $intro, ':krid' => $krid));

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
    isset($_GET['destination']) ? $_GET['destination'] : 'kprojectreports',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

function kprojectreports_delete_form_submit($form, &$form_state) {
  kprojectreports_report_delete($form_state['values']['krid']);
  $form_state['redirect'] = 'kprojectreports';
}

function kprojectreports_report_delete($krid) {
  db_query('DELETE FROM {kprojectreports_schedules} WHERE krid = :krid', array(':krid' => $krid));
} 

/**
 * Preview a report on screen
 */
function kprojectreports_preview_form($form, $form_state, $report = NULL, $params = NULL) {
  $daterun = date('Y-m-d', time());
  $reporttitle = $report->title;

  include_once(drupal_get_path('module', 'kprojectreports') .'/' . $report->report . '.inc.php');

  if (isset($_REQUEST['daterun'])) {
    $daterun = check_plain($_REQUEST['daterun']);
  }

  // cheap token replacement in case there is [site-date] in the title
  $reporttitle = preg_replace('/\[site-date\]/', $daterun, $reporttitle);

  $form['krid'] = array(
    '#type' => 'value',
    '#value' => $report->krid,
  );

  $helpfunc = $report->report . '_datehelp';
  $helptext = "The help text for this field is not implemented. You have gone where no user has gone before.";

  if (function_exists($helpfunc)) {
    $helptext = $helpfunc();
  }

  $form['daterun'] = array(
    '#type' => 'date_popup',
    '#title' => t('Report run date'),
    '#date_format' => 'Y-m-d',
    '#default_value' => $daterun,
    '#description' => $helptext,
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
  $output = $reportfunc($date_start, $date_end, $report);

  $form['reportdata']['intro'] = array(
    '#type' => 'markup',
    '#markup' => '<p>' . $report->intro . '</p>',
  );

  $form['reportdata']['output'] = array(
    '#type' => 'markup',
    '#markup' => $output,
  );

  return $form;
}


function kprojectreports_preview_form_submit($form, &$form_state) {
  $reportid = $form_state['values']['krid'];
  $daterun = check_plain($form_state['values']['daterun']);
  $daterun = substr($daterun, 0, 10); // grab only the date part, not time

  $options = array(
    'query' => array(
      'daterun' => $daterun,
    ),
  );

  // Other parameters used in some reports (Ex: billable)
  if (isset($_REQUEST['uid_current'])) {
    $options['query']['uid_current'] = 1;
  }

  if (isset($_REQUEST['uid'])) {
    $options['query']['uid'] = $_REQUEST['uid'];
  }

  drupal_goto('kprojectreports/' . $reportid . '/preview', $options);
}

