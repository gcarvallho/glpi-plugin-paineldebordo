<?php

/**
 * PDF export for report_run.php — reuses GLPI core's own TCPDF (already a
 * required dependency of GLPI 11 itself, so guaranteed compatible with the
 * running PHP version) instead of vendoring a separate copy.
 */

if (class_exists('TCPDF') && !class_exists('Plugin_Paineldebordo_ReportPdf')) {
    class Plugin_Paineldebordo_ReportPdf extends TCPDF
    {
        public string $reportMeta = '';

        public function Header()
        {
            $this->SetFont('helvetica', 'B', 12);
            $this->SetY(8);
            $this->Cell(0, 6, 'Painel de bordo', 0, 1, 'L');
            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(102, 102, 102);
            $this->Cell(0, 4, $this->reportMeta, 0, 1, 'R');
            $this->SetTextColor(0, 0, 0);
            $this->SetLineWidth(0.4);
            $this->SetDrawColor(9, 20, 31);
            $this->Line(10, 18, $this->getPageWidth() - 10, 18);
            $this->SetY(22);
        }

        public function Footer()
        {
            $this->SetY(-12);
            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(136, 136, 136);
            $this->Cell(
                0,
                8,
                __('Page', 'paineldebordo') . ' ' . $this->getAliasNumPage() . ' ' . __('of', 'paineldebordo') . ' ' . $this->getAliasNbPages(),
                0,
                0,
                'R'
            );
        }
    }
}

/**
 * Stream a report as a landscape A4 PDF and exit. Reuses the exact
 * headers/rows the CSV export already computed, so the two stay in sync.
 *
 * @param array<int,string> $headers
 * @param array<int,array<int,mixed>> $rows
 */
function plugin_paineldebordo_report_pdf_output(string $title, array $headers, array $rows, string $period_label, string $filename): void
{
    if (!class_exists('Plugin_Paineldebordo_ReportPdf')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo __('PDF export is unavailable on this server.', 'paineldebordo');
        exit;
    }

    $firstname = trim((string) ($_SESSION['glpifirstname'] ?? ''));
    $realname = trim((string) ($_SESSION['glpirealname'] ?? ''));
    $user_name = trim($firstname . ' ' . $realname);
    if ($user_name === '') {
        $user_name = trim((string) ($_SESSION['glpiname'] ?? ''));
    }

    $pdf = new Plugin_Paineldebordo_ReportPdf('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->reportMeta = htmlspecialchars(
        __('Generated on', 'paineldebordo') . ' ' . date('d/m/Y H:i')
        . ($user_name !== '' ? ' ' . __('by', 'paineldebordo') . ' ' . $user_name : '')
        . ' · ' . $period_label . ' · ' . count($rows) . ' ' . __('records', 'paineldebordo')
    );
    $pdf->SetCreator('Painel de bordo');
    $pdf->SetAuthor($user_name !== '' ? $user_name : 'Painel de bordo');
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 24, 10);
    $pdf->SetAutoPageBreak(true, 16);
    $pdf->AddPage();

    $html = '<h3 style="margin-top:0;">' . htmlspecialchars($title) . '</h3>';
    $html .= '<table cellpadding="3" style="width:100%; font-size:9pt; border-collapse:collapse;">';
    $html .= '<thead><tr style="background-color:#09141F; color:#ffffff;">';
    foreach ($headers as $h) {
        $html .= '<th style="border:0.5pt solid #cccccc; text-align:left;">' . htmlspecialchars((string) $h) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    if (!$rows) {
        $html .= '<tr><td style="border:0.5pt solid #cccccc;" colspan="' . max(1, count($headers)) . '">'
            . htmlspecialchars(__('No data', 'paineldebordo')) . '</td></tr>';
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td style="border:0.5pt solid #cccccc;">' . htmlspecialchars((string) $cell) . '</td>';
            }
            $html .= '</tr>';
        }
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    // 'I' = open inline in the browser's own PDF viewer (preview first);
    // the viewer's own toolbar still lets the user save it from there.
    $pdf->Output($filename, 'I');
}
