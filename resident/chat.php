<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        redirect_to('../index.php');
    }
}

require_role('resident');

$page_title = "Live Chat";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

// Dynamically resolve admin user ID for use in JS
require_once '../config/database.php';
$stmtAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$adminUser = $stmtAdmin ? $stmtAdmin->fetch(PDO::FETCH_ASSOC) : null;
$admin_user_id = $adminUser ? (int)$adminUser['id'] : 1;
$chat_csrf_token = csrf_token();

require_once 'partials/header.php';
?>
<style>
    .chat-window {
        background-color: var(--card-bg);
        border-radius: 12px;
        box-shadow: 0 4px 12px var(--shadow-color);
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        height: calc(100vh - 150px); /* Adjust height for the page layout */
    }
    .chat-header {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
    }
    .chat-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
    }
     .chat-header i {
        font-size: 1.5rem;
        margin-right: 12px;
        color: var(--accent-green);
    }
    .messages-area {
        flex-grow: 1;
        padding: 20px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
    .message-input-area {
        padding: 20px;
        border-top: 1px solid var(--border-color);
        background-color: #f9fafb;
    }
    .message-input-form {
        display: flex;
        align-items: center;
    }
    .message-input-form input {
        flex-grow: 1;
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 12px 20px;
        font-size: 1rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .message-input-form input:focus {
        border-color: var(--accent-blue);
    }
    .message-input-form button {
        background-color: var(--accent-blue);
        color: var(--text-light);
        border: none;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        margin-left: 15px;
        cursor: pointer;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
    }
    .message-input-form button:hover {
        background-color: var(--accent-blue-dark);
    }
    .message-bubble {
        max-width: 70%;
        padding: 12px 18px;
        margin-bottom: 10px;
    }
    .message-bubble p {
        margin: 0;
    }
    .message-bubble-me {
        background-color: var(--accent-blue);
        color: var(--text-light);
        border-radius: 20px 20px 5px 20px;
        align-self: flex-end;
    }
    .message-bubble-other {
        background-color: #e5e7eb;
        color: var(--text-primary);
        border-radius: 20px 20px 20px 5px;
        align-self: flex-start;
    }
    
    @media (max-width: 767px) {
        .chat-window {
            height: calc(100vh - 140px - 70px); /* header + bottom nav */
            border-radius: 0;
            box-shadow: none;
        }
        .chat-header {
            padding: 14px 16px;
        }
        .chat-header h2 {
            font-size: 1.2rem;
        }
        .messages-area {
            padding: 12px;
        }
        .message-input-area {
            padding: 10px 12px;
        }
        .message-bubble {
            max-width: 85%;
            padding: 10px 14px;
        }
    }
</style>
<div class="chat-window">
    <div class="chat-header">
        <i class="fas fa-users"></i>
        <h2>Chat with Barangay Admin</h2>
    </div>
    <div class="messages-area" id="messages-area">
        <!-- Messages will be loaded here by JavaScript -->
    </div>
    <div class="message-input-area">
        <form class="message-input-form" id="chat-form">
            <input type="text" id="message-input" placeholder="Type your message..." autocomplete="off">
            <button type="submit"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesArea = document.getElementById('messages-area');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    
    const ADMIN_ID = <?php echo $admin_user_id; ?>;
    const CHAT_CSRF_TOKEN = <?php echo json_encode($chat_csrf_token); ?>;
    let lastMessageId = 0;

    function escapeHTML(str) {
        return str.replace(/[&<>"']/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[match]);
    }

    function renderMessage(message, isMe) {
        const bubble = document.createElement('div');
        bubble.classList.add('message-bubble', isMe ? 'message-bubble-me' : 'message-bubble-other');
        const timestamp = message.sent_at ? new Date(message.sent_at).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        }) : '';
        const statusLabel = isMe ? (message.is_read ? 'Read' : 'Sent') : '';
        const meta = timestamp ? `${timestamp}${isMe ? ' • ' + statusLabel : ''}` : (isMe ? statusLabel : '');
        bubble.innerHTML = `<p>${escapeHTML(message.message)}</p>${meta ? `<p style="font-size:11px;opacity:.75;margin-top:4px;">${escapeHTML(meta)}</p>` : ''}`;
        messagesArea.appendChild(bubble);
    }

    async function fetchMessages() {
        try {
            const response = await fetch(`../api/chat.php?action=get_messages&partner_id=${ADMIN_ID}`);
            const data = await response.json();

            if (data.success) {
                messagesArea.innerHTML = '';
                const myUserId = <?php echo $_SESSION['user_id']; ?>;
                if(data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        renderMessage(msg, msg.sender_id == myUserId);
                    });
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                }
            }
        } catch (error) {
            console.error("Error fetching messages:", error);
        }
    }

    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const messageText = messageInput.value.trim();
        if (!messageText) return;

        messageInput.value = '';
        
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('message', messageText);
        formData.append('receiver_id', ADMIN_ID);
        formData.append('csrf_token', CHAT_CSRF_TOKEN);

        try {
            const response = await fetch('../api/chat.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                // Instantly render the sent message
                renderMessage({ message: messageText }, true);
                messagesArea.scrollTop = messagesArea.scrollHeight;
            } else {
                console.error("Failed to send message:", data.error);
                messageInput.value = messageText; // Restore message on failure
            }
        } catch (error) {
            console.error("Error sending message:", error);
        }
    });

    // Initial fetch
    fetchMessages();

    // Poll for new messages every 4 seconds to reduce API load.
    setInterval(fetchMessages, 4000);
});
</script>
<?php require_once 'partials/footer.php'; ?> 