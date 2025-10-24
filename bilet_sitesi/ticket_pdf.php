<?php
session_start();
require_once 'includes/config.php';
require_once 'fpdf/fpdf.php'; // FPDF kütüphanesi (örneğin fpdf klasöründe)

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    die("Geçersiz istek.");
}

$ticket_id = $_GET['id'];

// 🧾 Bilet bilgilerini veritabanından al
$stmt = $db->prepare("
    SELECT 
        t.id AS ticket_id,
        t.total_price,
        t.created_at,
        u.full_name,
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tr.arrival_time,
        bc.name AS company_name,
        GROUP_CONCAT(bs.seat_number, ', ') AS seats
    FROM Tickets t
    JOIN User u ON t.user_id = u.id
    JOIN Trips tr ON t.trip_id = tr.id
    JOIN Bus_Company bc ON tr.company_id = bc.id
    LEFT JOIN Booked_Seats bs ON t.id = bs.ticket_id
    WHERE t.id = :ticket_id AND t.user_id = :user_id
    GROUP BY t.id
");
$stmt->execute([':ticket_id' => $ticket_id, ':user_id' => $_SESSION['user_id']]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Bilet bulunamadı veya erişim yetkiniz yok.");
}

// 🧾 PDF oluştur
$pdf = new FPDF();
$pdf->AddPage();

// Başlık
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Bilet Bilgileri', 0, 1, 'C');
$pdf->Ln(10);

// Yolcu Bilgisi
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, 'Yolcu Adi:', 0, 0);
$pdf->Cell(100, 10, $ticket['full_name'], 0, 1);

$pdf->Cell(50, 10, 'Firma:', 0, 0);
$pdf->Cell(100, 10, $ticket['company_name'], 0, 1);

$pdf->Cell(50, 10, 'Kalkis Sehri:', 0, 0);
$pdf->Cell(100, 10, $ticket['departure_city'], 0, 1);

$pdf->Cell(50, 10, 'Varis Sehri:', 0, 0);
$pdf->Cell(100, 10, $ticket['destination_city'], 0, 1);

$pdf->Cell(50, 10, 'Kalkis Zamani:', 0, 0);
$pdf->Cell(100, 10, date('d-m-Y H:i', strtotime($ticket['departure_time'])), 0, 1);

$pdf->Cell(50, 10, 'Varis Zamani:', 0, 0);
$pdf->Cell(100, 10, date('d-m-Y H:i', strtotime($ticket['arrival_time'])), 0, 1);

$pdf->Cell(50, 10, 'Koltuk No:', 0, 0);
$pdf->Cell(100, 10, $ticket['seats'], 0, 1);

$pdf->Cell(50, 10, 'Odenen Tutar:', 0, 0);
$pdf->Cell(100, 10, $ticket['total_price'] . ' TL', 0, 1);

$pdf->Cell(50, 10, 'Satin Alim Tarihi:', 0, 0);
$pdf->Cell(100, 10, date('d-m-Y H:i', strtotime($ticket['created_at'])), 0, 1);

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'Bu bilet sistem tarafindan otomatik olarak olusturulmustur.', 0, 1, 'C');

// PDF çıktısı
$pdf->Output('I', 'Bilet_' . $ticket_id . '.pdf');
exit;
?>
