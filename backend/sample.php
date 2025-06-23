<?php
require('fpdf186/fpdf.php');

class PDF extends FPDF {
    function Header() {
        // Add logo or university name at the top (you can add an image if needed)
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'CAVITE STATE UNIVERSITY - IMUS CAMPUS', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Extension Services Office', 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer() {
        // Page number at the bottom
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'CERTIFICATION', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 10, 'This is to certify that the faculty members listed below from the Department of Computer Studies, Cavite State University – Imus Campus, have participated in and accomplished the corresponding number of Extension workload hours as part of their involvement in officially recognized Extension activities/programs/projects for the period:');
$pdf->Ln(5);

$pdf->Cell(30, 10, 'From:', 0, 0);
$pdf->Cell(0, 10, '________________________', 0, 1);
$pdf->Cell(30, 10, 'To:', 0, 0);
$pdf->Cell(0, 10, '________________________', 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Name of Faculty Member', 1, 0, 'C');
$pdf->Cell(50, 10, 'Extension Activity/Project Title', 1, 0, 'C');
$pdf->Cell(40, 10, 'Role/Designation', 1, 0, 'C');
$pdf->Cell(30, 10, 'No. of Hours Rendered', 1, 0, 'C');
$pdf->Cell(40, 10, 'Date/s of Implementation', 1, 1, 'C');

// Add rows (example data)
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 10, '', 1, 0, 'C');
$pdf->Cell(50, 10, '', 1, 0, 'C');
$pdf->Cell(40, 10, '', 1, 0, 'C');
$pdf->Cell(30, 10, '', 1, 0, 'C');
$pdf->Cell(40, 10, '', 1, 1, 'C');

$pdf->Ln(10);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Total Department Extension Hours Rendered: ________________________', 0, 1);
$pdf->Ln(10);

$pdf->MultiCell(0, 10, 'This certification is issued for record purposes and submission to the Office of the Extension Services and the Campus Extension Coordinator.');
$pdf->Ln(5);

$pdf->Cell(0, 10, 'Issued this ___ day of _________ 2025 at Cavite State University – Imus Campus, Imus City, Cavite.', 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Certified by:', 0, 1);
$pdf->Ln(5);
$pdf->Cell(0, 10, 'GRACE S. IBAÑEZ', 0, 1);
$pdf->Cell(0, 10, 'Chair, Department of Computer Studies', 0, 1);

$pdf->Output();
?>