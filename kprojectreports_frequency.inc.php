<?php

/*
 * In this file:
 * - functions to add config options to the "schedule new/existing report" forms
 * - functions to determine whether it is time to run
 */

//
// ******* Functions to configure details about the frequency
//

function kprojectreports_frequency_editreport_week(&$form_state, &$form, $data) {
  $form['option_week_firstday'] = array(
    '#type' => 'select',
    '#title' => 'First day of the week',
    '#options' => array(
      0 => '',
      1 => t('Monday'),
      2 => t('Tuesday'),
      3 => t('Wednesday'),
      4 => t('Thursday'),
      5 => t('Friday'),
      6 => t('Saturday'),
      7 => t('Sunday'),
    ),
    '#default_value' => $data['options']['week_firstday'],
    '#required' => TRUE,
  );
}

function kprojectreports_frequency_editreport_week2(&$form_state, &$form, $data) {
  $form['option_week2_firstday'] = array(
    '#type' => 'select',
    '#title' => 'First day of the week',
    '#options' => array(
      0 => '',
      1 => t('Monday'),
      2 => t('Tuesday'),
      3 => t('Wednesday'),
      4 => t('Thursday'),
      5 => t('Friday'),
      6 => t('Saturday'),
      7 => t('Sunday'),
    ),
    '#default_value' => $data['options']['week2_firstday'],
    '#required' => TRUE,
  );

  $form['option_week2_firstweek'] = array(
    '#type' => 'select',
    '#title' => 'First pay week in the year',
    '#description' => 'This will be used to calculate subsequent pay days in the year. For example, if next week is week 35 (of 52) and you need a report for that date, 35 % 2 = 1, so select Week 1.',
    '#options' => array (
      0 => '',
      1 => t('Week') . ' 1',
      2 => t('Week') . ' 2',
    ),
    '#default_value' => $data['options']['week2_firstweek'],
    '#required' => TRUE,
  );
}

function kprojectreports_frequency_editreport_quarter(&$form_state, &$form, $data) {
  $form['option_quarter_yearstart'] = array(
    '#type' => 'select',
    '#title' => 'First month of financial year',
    '#description' => 'Your quarters will be calculated automatically from that date.',
    '#default_value' => $data['options']['quarter_yearstart'],
    '#options' => array(
      0 => '',
      1 => t('January'),
      2 => t('February'),
      3 => t('March'),
      4 => t('April'),
      5 => t('May'),
      6 => t('June'),
      7 => t('July'),
      8 => t('August'),
      9 => t('September'),
      10 => t('October'),
      11 => t('November'),
      12 => t('December'),
    ),
    '#required' => TRUE,
  );
}

//
// ******* Functions to determine whether it is time to run
//

function kprojectreports_frequency_timetorun_day($report) {
  $timetorun = FALSE;
  $now = time();

  // Run around 5h on the next day, check if day is smaller than today
  if (! $report->lastrun
     || (date('H', $now) < 5) && ($report->lastrun < $now - 60 * 60 * 23))
  {
    $timetorun = TRUE;
    $date_start = mktime(0, 0, 0, date('m', $now), date('d', $now) - 1, date('Y', $now)); // 0h, yesterday
    $date_end   = mktime(23, 59, 59, date('m', $now), date('d', $now) - 1, date('Y', $now)); // 23h59, yesterday
  }

  return array($timetorun, $date_start, $date_end);
}

function kprojectreports_frequency_timetorun_week($report) {
  $timetorun = FALSE;
  $now = time();

  // Run on "first day" (configurable by report) around 4h, if we haven't already ran lately
  if (! $report->lastrun
     || (date('N', $now) == 1 && (date('H', $now) < 5) && ($report->lastrun < $now - 60 * 60 * 24)))
  {
    $timetorun = TRUE;
    $date_end   = mktime(23, 59, 59, date('m', $now), date('d', $now) - 1, date('Y', $now)); // 23h59, yesterday
    $date_start = $date_end - (60 * 60 * 24 * 7);
  }

  return array($timetorun, $date_start, $date_end);
}

function kprojectreports_frequency_timetorun_week2($report) {
  $timetorun = FALSE;
  $now = time();

  // Run on Monday around 4h, if we haven't already ran lately
  $is_week2 = (date('W') % 2 == $report->options['week2_firstweek'] % 2);

  if (! $report->lastrun
     || (date('N', $now) == $report->options['week_firstday'] && (date('H', $now) < 5) && ($report->lastrun < $now - 60 * 60 * 24)))
  {
    $timetorun = TRUE;
    $date_end   = mktime(23, 59, 59, date('m', $now), date('d', $now) - 1, date('Y', $now)); // 23h59, yesterday
    $date_start = $date_end - (60 * 60 * 24 * 14);
  }

  return array($timetorun, $date_start, $date_end);
}

function kprojectreports_frequency_timetorun_month($report) {
  $timetorun = FALSE;
  $now = time();

  // Run on the first day of the month, in the morning, if we haven't already ran today
  if (! $report->lastrun
     || (date('d', $now) == 1 && $report->lastrun < $now - 60 * 60 * 24))
  {
    $timetorun = TRUE;
    $date_start = mktime(0, 0, 0, date('m', $now) - 1, 1, date('Y', $now)); // first day of last current month
    $date_end   = mktime(23, 59, 59, date('m', $now) - 1, 31, date('Y', $now)); // last day of current month
  }

  return array($timetorun, $date_start, $date_end);
}

function kprojectreports_frequency_timetorun_quarter($report) {
  $timetorun = FALSE;
  $now = time();

  // Run on the first day of the month, in the morning, if we haven't already ran today
  $is_new_quarter = (date('n') % 3 == $report->options['quarter_yearstart'] % 3);

  if (! $report->lastrun
     || (date('j', $now) == 1 && $is_new_quarter && $report->lastrun < $now - 60 * 60 * 24))
  {
    $timetorun = TRUE;
    $date_start = mktime(0, 0, 0, date('m', $now) - 1, 1, date('Y', $now)); // first day of last current month
    $date_end   = mktime(23, 59, 59, date('m', $now) - 1, 31, date('Y', $now)); // last day of current month
  }

  return array($timetorun, $date_start, $date_end);
}

function kprojectreports_frequency_timetorun_year($report) {
  $timetorun = FALSE;
  $now = time();

  // TODO: make "first day of year" configurable. For now, is September 1st
  if (! $report->lastrun
     || (date('md', mktime()) == '0701' && $report->lastrun < mktime() - 60 * 60 * 24))
  {
    $timetorun = TRUE;
  }

  return array($timetorun, $date_start, $date_end);
}

