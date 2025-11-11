<?php
// CRITICAL: NO WHITESPACE OR OUTPUT BEFORE THIS LINE!
// This prevents InfinityFree from injecting ads

// Disable error display (prevents any PHP errors from breaking XML)
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to capture everything
ob_start();

// Start session AFTER output buffering
session_start();

// Twilio credentials
$accountSid = 'ACf4f0885ff9b8c1f68f32d48cc2c03481';
$authToken = 'a85437bce5ecf80102cfacd1a277ef66';
$ownerWhatsApp = 'whatsapp:+918200942022';
$twilioNumber = 'whatsapp:+14155238886';

// Get incoming message from WhatsApp
$incomingMsg = isset($_POST['Body']) ? trim($_POST['Body']) : '';
$from = isset($_POST['From']) ? $_POST['From'] : '';

// Load user session or create new one
if (!isset($_SESSION[$from])) {
    $_SESSION[$from] = ['step' => 0];
}
$session = &$_SESSION[$from];

$replyMsg = '';

// Conversation flow based on current step
switch($session['step']) {
    case 0: // Welcome message
        $replyMsg = "👋 Welcome to *RAJ Appliances Customer Support*!\n\n";
        $replyMsg .= "I'm here to help you register a complaint.\n\n";
        $replyMsg .= "Please provide your *full name*:";
        $session['step'] = 1;
        break;
        
    case 1: // Collect name
        $session['name'] = $incomingMsg;
        $replyMsg = "Thank you, {$session['name']}! 😊\n\n";
        $replyMsg .= "Which product do you have a complaint about?\n\n";
        $replyMsg .= "Please specify:\n";
        $replyMsg .= "- Product name (e.g., Mixer, Fan, Iron)\n";
        $replyMsg .= "- Model number (if available)";
        $session['step'] = 2;
        break;
        
    case 2: // Collect product info
        $session['product'] = $incomingMsg;
        $replyMsg = "Got it! Your product: *{$session['product']}*\n\n";
        $replyMsg .= "Please describe your complaint or issue in detail:";
        $session['step'] = 3;
        break;
        
    case 3: // Collect complaint and create ticket
        $session['complaint'] = $incomingMsg;
        $session['ticketId'] = generateTicketId();
        $session['timestamp'] = date('Y-m-d H:i:s');
        
        // Save complaint to file
        saveComplaint($session, $from);
        
        // Confirmation message to customer
        $replyMsg = "✅ *Complaint Registered Successfully!*\n\n";
        $replyMsg .= "📋 *Ticket ID:* {$session['ticketId']}\n";
        $replyMsg .= "👤 *Name:* {$session['name']}\n";
        $replyMsg .= "📦 *Product:* {$session['product']}\n";
        $replyMsg .= "📝 *Issue:* {$session['complaint']}\n\n";
        $replyMsg .= "⏰ Our team will contact you within 24 hours.\n\n";
        $replyMsg .= "Please save your Ticket ID for reference.\n\n";
        $replyMsg .= "Type *NEW* to register another complaint.";
        
        // Notify shop owner
        notifyOwner($session, $from, $accountSid, $authToken, $twilioNumber, $ownerWhatsApp);
        
        $session['step'] = 4;
        break;
        
    case 4: // After complaint is registered
        if (strtolower($incomingMsg) === 'new') {
            $_SESSION[$from] = ['step' => 1];
            $replyMsg = "Starting a new complaint registration...\n\n";
            $replyMsg .= "Please provide your *full name*:";
        } else {
            $replyMsg = "Your complaint has been registered with Ticket ID: *{$session['ticketId']}*\n\n";
            $replyMsg .= "Type *NEW* to register another complaint.";
        }
        break;
        
    default:
        $replyMsg = "Something went wrong. Type *START* to begin again.";
        $_SESSION[$from] = ['step' => 0];
}

// ============ HELPER FUNCTIONS ============

function generateTicketId() {
    $timestamp = time();
    $random = rand(100, 999);
    return "TICKET-{$timestamp}-{$random}";
}

function saveComplaint($session, $phone) {
    $complaint = [
        'ticketId' => $session['ticketId'],
        'name' => $session['name'],
        'phone' => $phone,
        'product' => $session['product'],
        'complaint' => $session['complaint'],
        'timestamp' => $session['timestamp'],
        'status' => 'Open'
    ];
    
    if (!file_exists('complaints')) {
        mkdir('complaints', 0777, true);
    }
    
    $filename = "complaints/{$session['ticketId']}.json";
    file_put_contents($filename, json_encode($complaint, JSON_PRETTY_PRINT));
    
    $masterFile = 'complaints/all_complaints.txt';
    $line = date('Y-m-d H:i:s') . " | " . $session['ticketId'] . " | " . 
            $session['name'] . " | " . $session['product'] . " | " . 
            $session['complaint'] . "\n";
    file_put_contents($masterFile, $line, FILE_APPEND);
}

function notifyOwner($session, $phone, $accountSid, $authToken, $twilioNumber, $ownerWhatsApp) {
    $ownerMsg = "🚨 *NEW COMPLAINT RECEIVED*\n\n";
    $ownerMsg .= "📋 *Ticket ID:* {$session['ticketId']}\n";
    $ownerMsg .= "👤 *Customer:* {$session['name']}\n";
    $ownerMsg .= "📞 *Phone:* {$phone}\n";
    $ownerMsg .= "📦 *Product:* {$session['product']}\n";
    $ownerMsg .= "📝 *Complaint:* {$session['complaint']}\n";
    $ownerMsg .= "⏰ *Time:* {$session['timestamp']}\n\n";
    $ownerMsg .= "Please resolve this at the earliest!";
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
    
    $data = [
        'From' => $twilioNumber,
        'To' => $ownerWhatsApp,
        'Body' => $ownerMsg
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For some shared hosting
    
    $response = curl_exec($ch);
    curl_close($ch);
}

// ============ OUTPUT CLEAN XML ============

// Clear any buffered output (removes InfinityFree injections)
ob_end_clean();

// Send headers - MUST be after ob_end_clean()
header('Content-Type: text/xml; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Generate clean XML response
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<Response>' . "\n";
$xml .= '  <Message>' . htmlspecialchars($replyMsg, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Message>' . "\n";
$xml .= '</Response>';

// Output ONLY the XML
echo $xml;

// Prevent any further output
exit();
?>

