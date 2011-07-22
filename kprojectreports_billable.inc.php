<?php

function kprojectreports_billable($start, $end, $report) {
  $output = '';
  $exclude_clients = array('Koumbit'); // HARDCODE XXX FIXME

  $report_lines = kprojectreports_billable_get_summary_global($date_start, $date_end);

  $output .= '<table>';
  $output .= '<tr>'
           . '<th>' . t('Client') . '</th>'
           . '<th>' . t('Contract') . '</th>'
           . '<th>' . t('Billable'). '</th>'
           . '<th>' . t('Total work') . '</th>'
           . '<th>' . t('Estimate') . '</th>'
           . '<th>' . t('Percent (period/total)') . '</th>'
           . '</tr>';

  foreach ($report_lines as $client_name => $tmp) {
    foreach ($tmp as $key => $val) {
      if (! in_array($val['client_title'], $exclude_clients)) {
        if ($val['estimate'] > 0) {
          $pct_period = $val['hours_period'] / $val['estimate'] * 100;
          $pct_total  = $val['hours_total'] / $val['estimate'] * 100;
        } else {
          $pct_period = 0;
          $pct_total = 0;
        }

        $output .= '<tr>'
                 . '<td>' . $val['client_title'] . '</td>'
                 . '<td>' . $val['contract_title'] . '</td>'
                 . '<td>' . sprintf('%.2f', $val['hours_period']) . '</td>'
                 . '<td>' . sprintf('%.2f', $val['hours_total']) . '</td>'
                 . '<td ' . ($val['estimate'] ? '' : 'style="color: red;"') . '>' . sprintf('%.2f', $val['estimate']) . '</td>'
                 . '<td ' . ($pct_total > 100 ? 'style="color: red;"' : ($pct_total > 80 ? 'style="color: #AAAA00;"' : '')) . '>'
                 . sprintf('%.2f', $pct_period) . '%'
                 . ' / '
                 . sprintf('%.2f', $pct_total) . '%' 
                 . '</td>'
                 . '</tr>';
        $totalincomes += $val['hours_period'];
      }
    }
  }

  $output .= '</table>';

  return $output;
}

function kprojectreports_billable_get_summary_global($date_start, $date_end) {
  $report_lines = array();

  $sql = "SELECT ktask_kcontract_node.title, ktask_kcontract_node.nid, sum(kpunch.duration) / 60 / 60 as periodhours
          FROM {kpunch} kpunch
          LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
          INNER JOIN {users} users ON kpunch.uid = users.uid
          LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
          LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
          LEFT JOIN {kcontract} ON node_kpunch__ktask.parent = kcontract.nid
          WHERE kpunch.billable_client = 1
            AND kpunch.order_reference = 0
            AND kcontract.state NOT IN (" . KPROJECT_CONTRACT_STATE_CLOSED . ',' . KPROJECT_CONTRACT_STATE_LOST . ',' . KPROJECT_CONTRACT_STATE_CANCELED . ")
          GROUP BY ktask_kcontract_node.nid";

  $result = db_query($sql);

  while ($contract = db_fetch_object($result)) {
    // Fetch client name and estimate for contract
    $res2  = db_query('SELECT kcid, parent, estimate FROM {kcontract} WHERE nid = %d', $contract->nid);
    $contract_total = 0;

    if (($kcontract = db_fetch_object($res2))) {
      $client = db_result(db_query('SELECT title FROM {node} WHERE nid = %d', $kcontract->parent));

      // Fetch total number of hours worked on the project
      $sql3 = "SELECT sum(kpunch.duration) / 60 / 60 as totalhours
               FROM {kpunch} kpunch
               LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
               LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
               LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
               WHERE ktask_kcontract_node.nid = %d";
  
      $contract_total = db_result(db_query($sql3, $contract->nid));
    } else {
      $client = t("Error: Could not find the contract information!");
    }

    $report_lines[$client][] = array(
      'client_id'      => $clientid,
      'project_id'     => $projectid,
      'contract_title' => $contract->title,
      'client_title'   => $client,
      'hours_period'   => $contract->periodhours,
      'hours_total'    => $contract_total,
      'estimate'       => $kcontract->estimate,
    );
  }

  ksort($report_lines);
  return $report_lines;
}
