<?php
// config/database.php - Database Configuration
class Database {
    private $host = "localhost";
    private $db_name = "nagarsevak_appointments";
    private $username = "root"; // Change to your MySQL username
    private $password = "bhushu";     // Change to your MySQL password
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// api/book-appointment.php - Main booking endpoint
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['fullName', 'profession', 'reason', 'contactNumber'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize inputs
    $fullName = trim($input['fullName']);
    $profession = trim($input['profession']);
    $reason = trim($input['reason']);
    $contactNumber = trim($input['contactNumber']);
    $urgentContact = isset($input['urgentContact']) ? (bool)$input['urgentContact'] : false;
    
    // Generate booking reference
    $bookingReference = 'CHG' . date('Ymd') . rand(1000, 9999);
    
    // Calculate appointment date
    $appointmentDate = calculateAppointmentDate($urgentContact);
    
    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Insert appointment
    $query = "INSERT INTO appointments 
              (full_name, profession, reason, contact_number, urgent_contact, appointment_date, booking_reference) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $fullName,
        $profession, 
        $reason,
        $contactNumber,
        $urgentContact,
        $appointmentDate,
        $bookingReference
    ]);
    
    // Get the inserted appointment
    $appointmentId = $db->lastInsertId();
    
    // Send response
    echo json_encode([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'data' => [
            'appointment_id' => $appointmentId,
            'booking_reference' => $bookingReference,
            'appointment_date' => $appointmentDate,
            'full_name' => $fullName,
            'profession' => $profession,
            'contact_number' => $contactNumber,
            'urgent_contact' => $urgentContact
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function calculateAppointmentDate($isUrgent) {
    $now = new DateTime();
    
    if ($isUrgent) {
        // Same day or next day for urgent
        $appointmentDate = clone $now;
        $appointmentDate->setTime(14, 0); // 2 PM
        
        // If it's past 2 PM, schedule for next day
        if ($now->format('H') >= 14) {
            $appointmentDate->add(new DateInterval('P1D'));
        }
    } else {
        // 2-5 days for regular appointments
        $appointmentDate = clone $now;
        $daysToAdd = rand(2, 5);
        $appointmentDate->add(new DateInterval('P' . $daysToAdd . 'D'));
        $appointmentDate->setTime(10, 0); // 10 AM
    }
    
    // Skip weekends
    while ($appointmentDate->format('w') == 0 || $appointmentDate->format('w') == 6) {
        $appointmentDate->add(new DateInterval('P1D'));
    }
    
    return $appointmentDate->format('Y-m-d H:i:s');
}

// api/get-appointments.php - For admin to view appointments
<?php
header('Content-Type: application/json');
include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    // Build query
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($status)) {
        $whereClause .= " AND status = ?";
        $params[] = $status;
    }
    
    if (!empty($date)) {
        $whereClause .= " AND DATE(appointment_date) = ?";
        $params[] = $date;
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM appointments $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get appointments
    $query = "SELECT * FROM appointments 
              $whereClause 
              ORDER BY created_at DESC 
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $appointments,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// api/update-appointment.php - Update appointment status
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT');
include_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || !isset($input['status'])) {
        throw new Exception('Missing required fields: id, status');
    }
    
    $id = (int)$input['id'];
    $status = $input['status'];
    
    // Validate status
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE appointments SET status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$status, $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Appointment updated successfully'
        ]);
    } else {
        throw new Exception('Appointment not found');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
