<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!is_logged_in() || !in_array($_SESSION['role'], ['admin'])) {
    redirect_to('../index.php');
}

$page_title = "Chat Management";

// --- Placeholder Data ---
$conversations = [
    ['id' => 1, 'fullname' => 'John Doe', 'last_message' => 'Hello, I need assistance with my barangay clearance.', 'timestamp' => '10:45 AM', 'unread' => 2],
    ['id' => 2, 'fullname' => 'Jane Smith', 'last_message' => 'Thank you for the quick response!', 'timestamp' => 'Yesterday', 'unread' => 0],
    ['id' => 3, 'fullname' => 'Peter Jones', 'last_message' => 'Can I ask about the schedule for garbage collection?', 'timestamp' => '2 days ago', 'unread' => 0],
];

$active_chat_partner = "John Doe";
$messages = [
    ['sender' => 'John Doe', 'text' => 'Hello, I need assistance with my barangay clearance.', 'time' => '10:45 AM', 'is_me' => false],
    ['sender' => 'Super Admin', 'text' => 'Good morning! How can I help you with your clearance?', 'time' => '10:46 AM', 'is_me' => true],
];

$chat_csrf_token = csrf_token();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-container { height: calc(100vh - 8rem); }
        .message-bubble-me { background-color: #DBEAFE; border-radius: 1.5rem 1.5rem 0.25rem 1.5rem; }
        .message-bubble-other { background-color: #F3F4F6; border-radius: 1.5rem 1.5rem 1.5rem 0.25rem; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include '../partials/sidebar.php'; ?>

        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-hidden p-4 sm:p-6 lg:p-8">
                <div class="flex chat-container bg-white rounded-lg shadow-xl">
                    <!-- Left Panel: Conversation List -->
                    <div class="w-1/3 border-r border-gray-200 flex flex-col">
                        <div class="p-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold">Conversations</h2>
                            <div class="relative mt-2">
                                <div class="flex items-center bg-gray-100 rounded-lg pl-3 pr-4 py-2">
                                    <i class="fas fa-search text-gray-400 text-lg"></i>
                                    <input type="text" placeholder="Search residents..." class="w-full bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 ml-2" />
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto" id="conversation-list">
                            <!-- Conversations will be loaded here -->
                        </div>
                    </div>

                    <!-- Right Panel: Chat Window -->
                    <div id="chat-window" class="w-2/3 flex flex-col hidden">
                        <!-- Chat Header -->
                        <div class="p-4 border-b border-gray-200 flex items-center">
                            <div id="chat-partner-avatar" class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-lg mr-3">
                            </div>
                            <h2 id="chat-partner-name" class="text-lg font-semibold"></h2>
                        </div>

                        <!-- Messages Area -->
                        <div id="messages-area" class="flex-1 p-6 overflow-y-auto space-y-4">
                            <!-- Messages will load here -->
                        </div>

                        <!-- Message Input -->
                        <div class="p-4 bg-gray-50 border-t border-gray-200">
                            <form id="chat-form" class="relative flex items-center">
                                <input type="text" id="message-input" placeholder="Type your message..." class="w-full pr-16 py-3 px-4 bg-white border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500" autocomplete="off">
                                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 bg-blue-600 hover:bg-blue-700 text-white rounded-full w-10 h-10 flex items-center justify-center">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div id="placeholder-window" class="w-2/3 flex items-center justify-center bg-gray-50">
                        <div class="text-center text-gray-500">
                            <i class="fas fa-comments text-5xl mb-4"></i>
                            <p>Select a conversation to start chatting.</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const CHAT_CSRF_TOKEN = <?php echo json_encode($chat_csrf_token); ?>;
        const conversationList = document.getElementById('conversation-list');
        const chatWindow = document.getElementById('chat-window');
        const placeholderWindow = document.getElementById('placeholder-window');
        const messagesArea = document.getElementById('messages-area');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const partnerNameEl = document.getElementById('chat-partner-name');
        const partnerAvatarEl = document.getElementById('chat-partner-avatar');
        const searchInput = document.querySelector('input[placeholder="Search residents..."]');

        let activePartnerId = null;
        let conversationPollingInterval = null;
        let messagePollingInterval = null;
        const myUserId = <?php echo $_SESSION['user_id']; ?>;
        
        console.log('Chat initialized with user ID:', myUserId);
        console.log('User role:', '<?php echo $_SESSION['role']; ?>');

        function escapeHTML(str) {
            return str ? str.replace(/[&<>"']/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[match]) : '';
        }

        let allConversations = [];

        async function fetchConversations() {
            try {
                console.log('Fetching conversations...');
                const response = await fetch('../../api/chat.php?action=get_conversations');
                const data = await response.json();
                console.log('Conversations response:', data);
                if (data.success) {
                    allConversations = data.conversations;
                    renderConversations(allConversations);
                } else {
                    console.error('Failed to fetch conversations:', data.error);
                }
            } catch (error) {
                console.error('Error fetching conversations:', error);
            }
        }

        function renderConversations(conversations) {
            // Sort by latest message timestamp descending (most recent first)
            conversations.sort((a, b) => {
                // Use sent_at for sorting, fallback to 0 if missing
                const dateA = a.sent_at ? Date.parse(a.sent_at) : 0;
                const dateB = b.sent_at ? Date.parse(b.sent_at) : 0;
                return dateB - dateA;
            });
            conversationList.innerHTML = '';
            let firstDiv = null;
            conversations.forEach((convo, idx) => {
                const convoDiv = document.createElement('div');
                convoDiv.className = 'flex items-center p-4 cursor-pointer hover:bg-gray-50 border-b border-gray-200';
                convoDiv.dataset.userId = convo.user_id;
                convoDiv.innerHTML = `
                    <div class="w-12 h-12 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-xl mr-4">
                        ${escapeHTML(convo.fullname).charAt(0)}
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between">
                            <h3 class="font-semibold">${escapeHTML(convo.fullname)}</h3>
                        </div>
                        <!-- No message preview, only show name -->
                    </div>
                `;
                convoDiv.addEventListener('click', () => {
                    document.querySelectorAll('#conversation-list > div').forEach(el => el.classList.remove('bg-blue-50'));
                    convoDiv.classList.add('bg-blue-50');
                    selectConversation(convo.user_id, convo.fullname);
                });
                conversationList.appendChild(convoDiv);
                if (idx === 0) firstDiv = convoDiv;
            });
            // Auto-select the most recent chat if none is active
            if (!activePartnerId && firstDiv) {
                firstDiv.classList.add('bg-blue-50');
                selectConversation(firstDiv.dataset.userId, firstDiv.querySelector('h3').textContent);
            }
        }

        searchInput.addEventListener('input', function() {
            const value = searchInput.value.trim().toLowerCase();
            const filtered = allConversations.filter(convo =>
                convo.fullname.toLowerCase().includes(value)
            );
            renderConversations(filtered);
        });

        function renderMessage(message) {
            // In shared admin inbox mode, any admin-authored message should align to admin side.
            const senderRole = String(message.sender_role || '').toLowerCase();
            const isAdminMsg = senderRole === 'admin' || message.sender_id == myUserId;
            const bubble = document.createElement('div');
            bubble.className = `flex w-full mt-2 space-x-3 max-w-xs ${isAdminMsg ? 'ml-auto justify-end' : 'justify-start'}`;
            
            // Format timestamp
            const timestamp = message.sent_at ? new Date(message.sent_at).toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit' 
            }) : '';
            
            bubble.innerHTML = `
                <div>
                    <div class="${isAdminMsg ? 'bg-blue-600 text-white p-3 rounded-l-lg rounded-br-lg' : 'bg-gray-300 p-3 rounded-r-lg rounded-bl-lg'}">
                        <p class="text-sm">${escapeHTML(message.message)}</p>
                        <p class="text-xs ${isAdminMsg ? 'text-blue-100' : 'text-gray-500'} mt-1">${timestamp}</p>
                    </div>
                </div>
            `;
            messagesArea.appendChild(bubble);
        }
        
        async function fetchMessages(partnerId) {
            if (!partnerId) return;
            try {
                console.log('Fetching messages for partner:', partnerId);
                const response = await fetch(`../../api/chat.php?action=get_messages&partner_id=${partnerId}`);
                const data = await response.json();
                console.log('Messages response:', data);
                if (data.success) {
                    messagesArea.innerHTML = '';
                    data.messages.forEach(renderMessage);
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                } else {
                    console.error('Failed to fetch messages:', data.error);
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
            }
        }

        function startConversationPolling() {
            if (conversationPollingInterval) clearInterval(conversationPollingInterval);
            conversationPollingInterval = setInterval(fetchConversations, 8000);
        }

        function stopConversationPolling() {
            if (conversationPollingInterval) clearInterval(conversationPollingInterval);
        }

        function startMessagePolling(partnerId) {
            if (messagePollingInterval) clearInterval(messagePollingInterval);
            messagePollingInterval = setInterval(() => fetchMessages(partnerId), 3000);
        }

        function stopMessagePolling() {
            if (messagePollingInterval) clearInterval(messagePollingInterval);
        }

        function markMessagesRead(partnerId) {
            const fd = new FormData();
            fd.append('action', 'mark_as_read');
            fd.append('sender_id', partnerId);
            fd.append('csrf_token', CHAT_CSRF_TOKEN);
            fetch('../../api/chat.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(() => {
                    if (typeof updateUnreadBadge === 'function') updateUnreadBadge();
                })
                .catch(() => {});
        }

        function selectConversation(userId, fullname) {
            activePartnerId = userId;
            chatWindow.classList.remove('hidden');
            placeholderWindow.classList.add('hidden');
            partnerNameEl.textContent = fullname;
            partnerAvatarEl.textContent = fullname.charAt(0);
            fetchMessages(userId);
            stopMessagePolling();
            startMessagePolling(userId);
            // Mark all unread messages from this resident as read
            markMessagesRead(userId);
        }

        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const messageText = messageInput.value.trim();
            if (!messageText || !activePartnerId) return;

            console.log('Sending message:', { text: messageText, to: activePartnerId, from: myUserId });

            // Optimistic UI update
            const tempMsg = { message: messageText, sender_id: myUserId };
            renderMessage(tempMsg);
            messagesArea.scrollTop = messagesArea.scrollHeight;
            messageInput.value = '';

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', messageText);
            formData.append('receiver_id', activePartnerId);
            formData.append('csrf_token', CHAT_CSRF_TOKEN);

            try {
                console.log('Sending to API...');
                const response = await fetch('../../api/chat.php', { method: 'POST', body: formData });
                console.log('API response status:', response.status);
                const data = await response.json();
                console.log('API response data:', data);
                
                if (data.success) {
                    console.log('Message sent successfully!');
                    // Message sent successfully - refresh messages to get the real message from database
                    setTimeout(() => fetchMessages(activePartnerId), 100);
                } else {
                    console.error('Failed to send message:', data.error);
                    // Remove the optimistic message and show error
                    if (messagesArea.lastChild) {
                        messagesArea.removeChild(messagesArea.lastChild);
                    }
                    alert('Failed to send message: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending message:', error);
                // Remove the optimistic message and show error
                if (messagesArea.lastChild) {
                    messagesArea.removeChild(messagesArea.lastChild);
                }
                alert('Error sending message. Please try again.');
            }
        });

        fetchConversations();
        startConversationPolling();

        // Stop polling when leaving the page
        window.addEventListener('beforeunload', () => {
            stopConversationPolling();
            stopMessagePolling();
        });
    });
    </script>
</body>
</html> 