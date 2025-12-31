/**
 * Pagewright Chat Interface
 * Conversational AI-powered site editing
 */

(function() {
    'use strict';
    
    // State
    let conversationHistory = [];
    
    // DOM Elements
    const conversation = document.getElementById('conversation');
    const promptInput = document.getElementById('prompt-input');
    const sendBtn = document.getElementById('send-btn');
    const resetBtn = document.getElementById('reset-btn');
    const publishAllBtn = document.getElementById('publish-all-btn');
    
    // Initialize
    function init() {
        attachEventListeners();
        loadConversationFromSession();
    }
    
    // Event Listeners
    function attachEventListeners() {
        if (sendBtn) {
            sendBtn.addEventListener('click', handleSend);
        }
        
        if (promptInput) {
            promptInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    handleSend();
                }
            });
            
            promptInput.addEventListener('input', () => {
                if (sendBtn) {
                    sendBtn.disabled = !promptInput.value.trim();
                }
            });
        }
        
        if (resetBtn) {
            resetBtn.addEventListener('click', handleReset);
        }
        
        if (publishAllBtn) {
            publishAllBtn.addEventListener('click', handlePublishAll);
        }
    }
    
    // Handle send prompt
    async function handleSend() {
        const prompt = promptInput.value.trim();
        
        if (!prompt) return;
        
        // Add user message
        addMessage('user', prompt);
        
        // Clear input
        promptInput.value = '';
        sendBtn.disabled = true;
        
        // Show loading
        const loadingId = addLoadingMessage();
        
        // Send to API (smart prompt - LLM decides what to do)
        try {
            const response = await fetch('/pw-admin/api/edit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'smart_prompt',
                    prompt: prompt,
                    context: getConversationContext()
                })
            });
            
            const data = await response.json();
            
            // Remove loading
            removeLoadingMessage(loadingId);
            
            if (data.success) {
                // Add assistant response
                addAssistantResponse(data);
                saveConversationToSession();
            } else {
                // Show error
                addErrorMessage(data.error || 'Failed to process request');
            }
        } catch (error) {
            removeLoadingMessage(loadingId);
            addErrorMessage('Request failed: ' + error.message);
        }
    }
    
    // Handle reset conversation
    function handleReset() {
        if (conversationHistory.length === 0) return;
        
        if (!confirm('Start a new conversation? This will clear the current chat.')) {
            return;
        }
        
        conversationHistory = [];
        clearConversation();
        sessionStorage.removeItem('pagewright_conversation');
    }
    
    // Handle publish all
    async function handlePublishAll() {
        if (!confirm('Publish all pages to the live site?')) {
            return;
        }
        
        setButtonLoading(publishAllBtn, true);
        
        try {
            const response = await fetch('/pw-admin/api/edit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'publish_all'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const count = Array.isArray(data.published) ? data.published.length : 0;
                addSystemMessage(`✓ Successfully published ${count} page(s) to your live site!`);
            } else {
                addErrorMessage('Failed to publish: ' + data.error);
            }
        } catch (error) {
            addErrorMessage('Request failed: ' + error.message);
        } finally {
            setButtonLoading(publishAllBtn, false);
        }
    }
    
    // Add user message
    function addMessage(type, content) {
        const message = {
            type: type,
            content: content,
            timestamp: new Date().toISOString()
        };
        
        conversationHistory.push(message);
        renderMessage(message);
        scrollToBottom();
    }
    
    // Add assistant response
    function addAssistantResponse(data) {
        const message = {
            type: 'assistant',
            content: data.message || 'Changes applied successfully',
            changes: data.changes || [],
            changeset_id: data.changeset?.id,
            preview_url: data.preview_url,
            usage: data.usage,
            timestamp: new Date().toISOString()
        };
        
        conversationHistory.push(message);
        renderAssistantMessage(message);
        scrollToBottom();
    }
    
    // Add error message
    function addErrorMessage(error) {
        const message = {
            type: 'error',
            content: error,
            timestamp: new Date().toISOString()
        };
        
        conversationHistory.push(message);
        renderErrorMessage(message);
        scrollToBottom();
    }
    
    // Add system message
    function addSystemMessage(content) {
        const message = {
            type: 'system',
            content: content,
            timestamp: new Date().toISOString()
        };
        
        conversationHistory.push(message);
        renderSystemMessage(message);
        scrollToBottom();
    }
    
    // Add loading message
    function addLoadingMessage() {
        const id = 'loading-' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.className = 'message assistant';
        div.innerHTML = '<div class="message-bubble"><div class="loading-indicator">Thinking...</div></div>';
        conversation.appendChild(div);
        scrollToBottom();
        return id;
    }
    
    // Remove loading message
    function removeLoadingMessage(id) {
        const elem = document.getElementById(id);
        if (elem) {
            elem.remove();
        }
    }
    
    // Render message
    function renderMessage(message) {
        const div = document.createElement('div');
        div.className = `message ${message.type}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = message.content;
        
        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = formatTime(message.timestamp);
        
        div.appendChild(bubble);
        div.appendChild(time);
        conversation.appendChild(div);
    }
    
    // Render assistant message with changes
    function renderAssistantMessage(message) {
        const div = document.createElement('div');
        div.className = 'message assistant';
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        
        const response = document.createElement('div');
        response.className = 'assistant-response';
        
        // Summary
        const summary = document.createElement('div');
        summary.className = 'response-summary';
        summary.textContent = message.content;
        response.appendChild(summary);
        
        // Changes
        if (message.changes && message.changes.length > 0) {
            const changes = document.createElement('div');
            changes.className = 'response-changes';
            changes.innerHTML = '<h4>Changes Made:</h4><ul>' +
                message.changes.map(c => `<li>${escapeHtml(c)}</li>`).join('') +
                '</ul>';
            response.appendChild(changes);
        }
        
        // Token usage
        if (message.usage) {
            const usage = document.createElement('div');
            usage.className = 'token-usage';
            usage.textContent = `Tokens: ${message.usage.total_tokens || 0} (prompt: ${message.usage.prompt_tokens || 0}, completion: ${message.usage.completion_tokens || 0})`;
            response.appendChild(usage);
        }
        
        // Actions
        const actions = document.createElement('div');
        actions.className = 'response-actions';
        
        if (message.preview_url) {
            const previewLink = document.createElement('a');
            // Add ?preview=true so router loads from preview directory
            const url = new URL(message.preview_url, window.location.origin);
            url.searchParams.set('preview', 'true');
            previewLink.href = url.toString();
            previewLink.target = '_blank';
            previewLink.className = 'secondary';
            previewLink.textContent = 'View Preview →';
            actions.appendChild(previewLink);
        }
        
        response.appendChild(actions);
        
        bubble.appendChild(response);
        
        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = formatTime(message.timestamp);
        
        div.appendChild(bubble);
        div.appendChild(time);
        conversation.appendChild(div);
    }
    
    // Render error message
    function renderErrorMessage(message) {
        const div = document.createElement('div');
        div.className = 'message assistant';
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        
        const error = document.createElement('div');
        error.className = 'response-error';
        error.textContent = '⚠ ' + message.content;
        
        bubble.appendChild(error);
        
        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = formatTime(message.timestamp);
        
        div.appendChild(bubble);
        div.appendChild(time);
        conversation.appendChild(div);
    }
    
    // Render system message
    function renderSystemMessage(message) {
        const div = document.createElement('div');
        div.className = 'message assistant';
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.style.background = '#ecfdf5';
        bubble.style.color = '#065f46';
        bubble.textContent = message.content;
        
        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = formatTime(message.timestamp);
        
        div.appendChild(bubble);
        div.appendChild(time);
        conversation.appendChild(div);
    }
    
    // Clear conversation display
    function clearConversation() {
        const messages = conversation.querySelectorAll('.message');
        messages.forEach(msg => msg.remove());
    }
    
    // Get conversation context for API
    function getConversationContext() {
        return conversationHistory.slice(-5).map(m => ({
            type: m.type,
            content: m.content
        }));
    }
    
    // Save conversation to session storage
    function saveConversationToSession() {
        try {
            sessionStorage.setItem('pagewright_conversation', JSON.stringify(conversationHistory));
        } catch (e) {
            console.warn('Failed to save conversation:', e);
        }
    }
    
    // Load conversation from session storage
    function loadConversationFromSession() {
        try {
            const saved = sessionStorage.getItem('pagewright_conversation');
            if (saved) {
                conversationHistory = JSON.parse(saved);
                // Re-render messages
                conversationHistory.forEach(msg => {
                    if (msg.type === 'user') {
                        renderMessage(msg);
                    } else if (msg.type === 'assistant' && msg.changes) {
                        renderAssistantMessage(msg);
                    } else if (msg.type === 'error') {
                        renderErrorMessage(msg);
                    } else if (msg.type === 'system') {
                        renderSystemMessage(msg);
                    }
                });
                scrollToBottom();
            }
        } catch (e) {
            console.warn('Failed to load conversation:', e);
        }
    }
    
    // Scroll to bottom of conversation
    function scrollToBottom() {
        if (conversation) {
            conversation.scrollTop = conversation.scrollHeight;
        }
    }
    
    // Format timestamp
    function formatTime(isoString) {
        const date = new Date(isoString);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    // Set button loading state
    function setButtonLoading(button, loading) {
        if (!button) return;
        
        if (loading) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        } else {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
