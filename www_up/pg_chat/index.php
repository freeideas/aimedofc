<?php
require_once '../infrastructure/lib.php';
require_once '../infrastructure/include.php';

// Check for mock mode
$mock_mode = isset($_GET['mock']) && $_GET['mock'] === 'true';

if (!$mock_mode) {
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
    
    // Get all non-deleted conversations for this user, sorted by last message timestamp
    $stmt = $db->prepare("
        SELECT 
            c.conversation_id, 
            c.title, 
            c.created_at, 
            c.updated_at,
            MAX(m.timestamp) as last_message_time
        FROM conversations c
        LEFT JOIN chat_messages m ON c.conversation_id = m.conversation_id AND m.deleted = 0
        WHERE c.user_id = ? 
        AND (c.deleted_flag = 0 OR c.deleted_flag IS NULL)
        GROUP BY c.conversation_id
        ORDER BY 
            CASE 
                WHEN MAX(m.timestamp) IS NOT NULL THEN MAX(m.timestamp)
                ELSE c.updated_at
            END DESC
    ");
    $stmt->execute([$user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Mock mode - use fake data
    $patient_name = 'John Doe';
    $conversations = [
        ['conversation_id' => 'mock1', 'title' => 'Questions about test results', 'created_at' => '2024-01-15 10:00:00'],
        ['conversation_id' => 'mock2', 'title' => 'Medication side effects', 'created_at' => '2024-01-14 14:30:00']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Medical Office Assistant</title>
    <link rel="stylesheet" href="../infrastructure/glassmorphic.css">
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
                                <div class="conversation-content">
                                    <div class="conversation-title"><?= htmlspecialchars($conv['title']) ?></div>
                                    <div class="conversation-date"><?= date('M j, g:i A', strtotime($conv['updated_at'])) ?></div>
                                </div>
                                <button class="conversation-delete" data-id="<?= htmlspecialchars($conv['conversation_id']) ?>" title="Delete conversation">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="chat-main">
                <div class="chat-header">
                    <h2 id="chatTitle">New Conversation</h2>
                    <button id="clearChatBtn" class="btn-clear-chat" style="display: none;">Clear Chat</button>
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
            item.addEventListener('click', (e) => {
                // Don't trigger if clicking the delete button
                if (e.target.classList.contains('conversation-delete')) return;
                
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
        
        // Handle conversation deletion
        document.querySelectorAll('.conversation-delete').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const conversationId = btn.dataset.id;
                if (confirm('Are you sure you want to delete this conversation?')) {
                    await deleteConversation(conversationId);
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

            // Add user message to UI (temporarily without ID)
            const userMsgElement = addMessageToUI(message, 'patient');
            input.value = '';
            input.disabled = true;

            try {
                // Get browser's local datetime and timezone
                const now = new Date();
                const localDatetime = now.toLocaleString('en-US', { 
                    year: 'numeric', 
                    month: '2-digit', 
                    day: '2-digit', 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit',
                    hour12: false 
                });
                const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                
                const response = await fetch('api_chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        conversation_id: currentConversationId,
                        local_datetime: localDatetime,
                        timezone: timezone
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
                    
                    // Update user message with ID
                    if (data.user_message_id && userMsgElement) {
                        const deleteBtn = userMsgElement.querySelector('.message-delete');
                        if (!deleteBtn) {
                            // Add delete button now that we have the ID
                            const wrapper = userMsgElement.querySelector('.message-wrapper');
                            const delBtn = document.createElement('button');
                            delBtn.className = 'message-delete';
                            delBtn.setAttribute('data-message-id', data.user_message_id);
                            delBtn.setAttribute('title', 'Delete message');
                            delBtn.innerHTML = '×';
                            delBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                deleteMessage(data.user_message_id, userMsgElement);
                            });
                            wrapper.appendChild(delBtn);
                        }
                    }
                    
                    // Add AI response to UI with ID
                    addMessageToUI(data.response, 'assistant', data.ai_message_id);
                    
                    // Show clear chat button
                    document.getElementById('clearChatBtn').style.display = 'block';
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
                        addMessageToUI(msg.message, msg.role, msg.message_id);
                    });
                    
                    // Show clear chat button if there are messages
                    if (data.messages.length > 0) {
                        document.getElementById('clearChatBtn').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Error loading conversation:', error);
            }
        }

        function addMessageToUI(message, role, messageId = null) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            
            // Don't add delete button to the initial greeting
            const showDelete = messageId && role !== 'greeting';
            
            messageDiv.innerHTML = `
                <div class="message-wrapper">
                    <div class="message-content">${escapeHtml(message)}</div>
                    ${showDelete ? `<button class="message-delete" data-message-id="${messageId}" title="Delete message">×</button>` : ''}
                </div>
            `;
            
            if (showDelete) {
                messageDiv.querySelector('.message-delete').addEventListener('click', (e) => {
                    e.stopPropagation();
                    deleteMessage(messageId, messageDiv);
                });
            }
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            return messageDiv;
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
                <div class="conversation-content">
                    <div class="conversation-title">${escapeHtml(title)}</div>
                    <div class="conversation-date">Just now</div>
                </div>
                <button class="conversation-delete" data-id="${conversationId}" title="Delete conversation">×</button>
            `;
            
            // Add click handler for selection
            newConvDiv.addEventListener('click', (e) => {
                if (e.target.classList.contains('conversation-delete')) return;
                document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                newConvDiv.classList.add('active');
                loadConversation(conversationId);
            });
            
            // Add delete handler
            const deleteBtn = newConvDiv.querySelector('.conversation-delete');
            deleteBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                if (confirm('Are you sure you want to delete this conversation?')) {
                    await deleteConversation(conversationId);
                }
            });
            
            conversationsList.insertBefore(newConvDiv, conversationsList.firstChild);
            
            // Update chat title
            document.getElementById('chatTitle').textContent = title;
        }
        
        async function deleteConversation(conversationId) {
            try {
                const response = await fetch('api_delete_conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    // Remove from sidebar
                    const convItem = document.querySelector(`.conversation-item[data-id="${conversationId}"]`);
                    if (convItem) {
                        convItem.remove();
                    }
                    
                    // If this was the current conversation, start a new chat
                    if (currentConversationId === conversationId) {
                        startNewChat();
                    }
                    
                    // If no conversations left, show new chat item
                    const remainingConvs = document.querySelectorAll('.conversation-item');
                    if (remainingConvs.length === 0) {
                        const conversationsList = document.getElementById('conversationsList');
                        conversationsList.innerHTML = `
                            <div class="conversation-item active" data-id="new">
                                <div class="conversation-title">(new chat)</div>
                                <div class="conversation-date">Start a conversation</div>
                            </div>
                        `;
                        startNewChat();
                    }
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
                alert('Failed to delete conversation');
            }
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

        // Delete message function
        async function deleteMessage(messageId, messageElement) {
            if (!confirm('Are you sure you want to delete this message?')) return;
            
            try {
                const response = await fetch('api_delete_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message_id: messageId,
                        action: 'delete'
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    messageElement.remove();
                }
            } catch (error) {
                console.error('Error deleting message:', error);
            }
        }
        
        // Clear chat function
        document.getElementById('clearChatBtn').addEventListener('click', async () => {
            if (!currentConversationId) return;
            if (!confirm('Are you sure you want to clear all messages in this conversation? This cannot be undone.')) return;
            
            try {
                const response = await fetch('api_delete_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: currentConversationId,
                        action: 'clear'
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    // Clear all messages except the greeting
                    const messagesContainer = document.getElementById('chatMessages');
                    messagesContainer.innerHTML = `
                        <div class="message assistant">
                            <div class="message-content">
                                Hello ${patientName}! I am not a doctor, but I have read all your records and I am ready to answer any questions you have.
                            </div>
                        </div>
                    `;
                    document.getElementById('clearChatBtn').style.display = 'none';
                }
            } catch (error) {
                console.error('Error clearing chat:', error);
            }
        });
        
        // Check if in mock mode
        const urlParams = new URLSearchParams(window.location.search);
        const mockMode = urlParams.get('mock') === 'true';
        
        if (mockMode) {
            // Show mock messages for testing
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.innerHTML = '';
            
            // Add initial greeting
            addMessageToUI(`Hello ${patientName}! I am not a doctor, but I have read all your records and I am ready to answer any questions you have.`, 'assistant');
            
            // Add mock conversation
            setTimeout(() => {
                addMessageToUI('What are my hemoglobin levels from my recent blood work?', 'patient', 'mock_msg_1');
            }, 500);
            
            setTimeout(() => {
                addMessageToUI('Based on your recent blood work from January 15, 2024, your hemoglobin level is 14.5 g/dL, which is within the normal range. The normal range for adult males is typically 13.5-17.5 g/dL. Your results indicate healthy red blood cell production and oxygen-carrying capacity.', 'assistant', 'mock_msg_2');
            }, 1000);
            
            setTimeout(() => {
                addMessageToUI('Should I be concerned about any of my test results?', 'patient', 'mock_msg_3');
            }, 1500);
            
            setTimeout(() => {
                addMessageToUI('While I cannot provide medical advice as I am not a doctor, I can tell you that your blood work shows all values within normal ranges, including your white blood cell count at 7,500/μL. However, I recommend discussing these results with Dr. Smith during your next appointment for a complete medical interpretation and any necessary follow-up actions.', 'assistant', 'mock_msg_4');
            }, 2000);
            
            // Show clear button since we have messages
            document.getElementById('clearChatBtn').style.display = 'block';
        } else {
            // Auto-select first conversation or new chat on load
            const firstItem = document.querySelector('.conversation-item');
            if (firstItem) {
                firstItem.click();
            }
        }
    </script>
</body>
</html>