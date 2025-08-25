<?php
require_once '../infrastructure/lib.php';
$user_id = checkAuth();
if (!$user_id) {
    header('Location: /pg_login/');
    exit;
}

$db = getAppDb();

// Get patient name
$stmt = $db->prepare("SELECT full_name FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_name = $patient ? $patient['full_name'] : 'Patient';

// Get all conversations for this user
$stmt = $db->prepare("
    SELECT conversation_id, title, created_at, updated_at 
    FROM conversations 
    WHERE user_id = ? 
    ORDER BY updated_at DESC
");
$stmt->execute([$user_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Medical Office Assistant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <h1>Medical Office Assistant</h1>
                    <p class="subtitle">AI-Powered Medical Consultation</p>
                </div>
                <div class="header-actions">
                    <a href="../pg_main/" class="btn-secondary">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="chat-container">
            <aside class="conversations-sidebar">
                <div class="sidebar-header">
                    <h2>Conversations</h2>
                    <button id="newChatBtn" class="btn-new-chat">+ New Chat</button>
                </div>
                <div class="conversations-list" id="conversationsList">
                    <?php if (empty($conversations)): ?>
                        <div class="conversation-item active" data-id="new">
                            <div class="conversation-title">(new chat)</div>
                            <div class="conversation-date">Start a conversation</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item" data-id="<?= htmlspecialchars($conv['conversation_id']) ?>">
                                <div class="conversation-title"><?= htmlspecialchars($conv['title']) ?></div>
                                <div class="conversation-date"><?= date('M j, g:i A', strtotime($conv['updated_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="chat-main">
                <div class="chat-header">
                    <h2 id="chatTitle">New Conversation</h2>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <div class="message assistant">
                        <div class="message-content">
                            Hello <?= htmlspecialchars($patient_name) ?>! I am not a doctor, but I have read all your records and I am ready to answer any questions you have.
                        </div>
                    </div>
                </div>
                <div class="chat-input-container">
                    <form id="chatForm" class="chat-form">
                        <input type="text" 
                               id="messageInput" 
                               class="message-input" 
                               placeholder="Type your question here..." 
                               autocomplete="off"
                               required>
                        <button type="submit" class="btn-send">Send</button>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script>
        const patientName = <?= json_encode($patient_name) ?>;
        let currentConversationId = null;
        let isNewChat = true;

        // Handle conversation selection
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                
                const convId = item.dataset.id;
                if (convId === 'new') {
                    startNewChat();
                } else {
                    loadConversation(convId);
                }
            });
        });

        // Handle new chat button
        document.getElementById('newChatBtn')?.addEventListener('click', () => {
            startNewChat();
        });

        // Handle message submission
        document.getElementById('chatForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            if (!message) return;

            // Add user message to UI
            addMessageToUI(message, 'patient');
            input.value = '';
            input.disabled = true;

            try {
                const response = await fetch('api_chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        conversation_id: currentConversationId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    // Update conversation ID if this was a new chat
                    if (!currentConversationId && data.conversation_id) {
                        currentConversationId = data.conversation_id;
                        isNewChat = false;
                        
                        // Update sidebar with new conversation
                        updateSidebarWithNewConversation(data.conversation_id, data.title || message.substring(0, 50));
                    }
                    
                    // Add AI response to UI
                    addMessageToUI(data.response, 'assistant');
                } else {
                    addMessageToUI('Sorry, there was an error processing your request. Please try again.', 'assistant error');
                }
            } catch (error) {
                console.error('Error:', error);
                addMessageToUI('Sorry, there was an error connecting to the server. Please try again.', 'assistant error');
            } finally {
                input.disabled = false;
                input.focus();
            }
        });

        function startNewChat() {
            currentConversationId = null;
            isNewChat = true;
            document.getElementById('chatTitle').textContent = 'New Conversation';
            document.getElementById('chatMessages').innerHTML = `
                <div class="message assistant">
                    <div class="message-content">
                        Hello ${patientName}! I am not a doctor, but I have read all your records and I am ready to answer any questions you have.
                    </div>
                </div>
            `;
            
            // Update sidebar selection
            document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
            const newChatItem = document.querySelector('.conversation-item[data-id="new"]');
            if (newChatItem) {
                newChatItem.classList.add('active');
            }
        }

        async function loadConversation(conversationId) {
            currentConversationId = conversationId;
            isNewChat = false;
            
            try {
                const response = await fetch(`api_get_conversation.php?id=${conversationId}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('chatTitle').textContent = data.title;
                    
                    // Clear and load messages
                    const messagesContainer = document.getElementById('chatMessages');
                    messagesContainer.innerHTML = '';
                    
                    // Always start with the greeting
                    addMessageToUI(`Hello ${patientName}! I am not a doctor, but I have read all your records and I am ready to answer any questions you have.`, 'assistant');
                    
                    // Add conversation messages
                    data.messages.forEach(msg => {
                        addMessageToUI(msg.message, msg.role);
                    });
                }
            } catch (error) {
                console.error('Error loading conversation:', error);
            }
        }

        function addMessageToUI(message, role) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            messageDiv.innerHTML = `<div class="message-content">${escapeHtml(message)}</div>`;
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function updateSidebarWithNewConversation(conversationId, title) {
            // Remove any existing "new chat" item
            const newChatItem = document.querySelector('.conversation-item[data-id="new"]');
            if (newChatItem) {
                newChatItem.remove();
            }
            
            // Add new conversation to top of list
            const conversationsList = document.getElementById('conversationsList');
            const newConvDiv = document.createElement('div');
            newConvDiv.className = 'conversation-item active';
            newConvDiv.dataset.id = conversationId;
            newConvDiv.innerHTML = `
                <div class="conversation-title">${escapeHtml(title)}</div>
                <div class="conversation-date">Just now</div>
            `;
            
            // Add click handler
            newConvDiv.addEventListener('click', () => {
                document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                newConvDiv.classList.add('active');
                loadConversation(conversationId);
            });
            
            conversationsList.insertBefore(newConvDiv, conversationsList.firstChild);
            
            // Update chat title
            document.getElementById('chatTitle').textContent = title;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Auto-select first conversation or new chat on load
        const firstItem = document.querySelector('.conversation-item');
        if (firstItem) {
            firstItem.click();
        }
    </script>
</body>
</html>