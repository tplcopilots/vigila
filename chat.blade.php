<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Web Chat Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bridge-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .bridge-head {
            padding: 14px 16px;
            border-bottom: 1px solid #eef2f7;
            font-weight: 600;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .chat-back-btn {
            margin-left: 12px;
        }

        .bridge-wrap {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 16px;
            transition: all 0.2s ease;
        }

        .bridge-wrap.expanded {
            grid-template-columns: 1fr;
        }

        .bridge-wrap.expanded .bridge-card:first-child {
            display: none;
        }

        .bridge-wrap.expanded .bridge-card:last-child {
            min-width: 0;
        }

        .conv-list {
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 4px;
        }

        .conv-list::-webkit-scrollbar {
            width: 8px;
        }

        .conv-list::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.45);
            border-radius: 999px;
        }

        .conv-item {
            display: flex;
            gap: 12px;
            padding: 14px;
            align-items: flex-start;
            border: 1px solid transparent;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            margin-bottom: 10px;
        }

        .conv-item:hover {
            border-color: #c7d2fe;
            background: #f8faff;
            transform: translateY(-1px);
        }

        .conv-item.active {
            background: #eef2ff;
            border-color: #c7d2fe;
        }

        .conv-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #e0e7ff;
            color: #1e3a8a;
            display: grid;
            place-items: center;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .conv-body {
            flex: 1;
            min-width: 0;
        }

        .conv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 6px;
        }

        .conv-id {
            font-weight: 700;
            color: #111827;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }

        .conv-time {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
        }

        .conv-preview {
            font-size: 13px;
            color: #4b5563;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.5;
        }

        .conv-badge {
            margin-left: 10px;
            padding: 4px 10px;
            background: #f3f4f6;
            color: #374151;
            font-size: 11px;
            border-radius: 999px;
            align-self: flex-start;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .bridge-reply {
            padding: 12px 16px;
            border-top: 1px solid #eef2f7;
            background: #fff;
            display: flex;
            align-items: flex-end;
            gap: 12px;
        }

        .bridge-reply .form-control {
            border-radius: 28px;
            border: 1px solid #d1d5db;
            box-shadow: none;
            resize: none;
            padding: 14px 18px;
            line-height: 1.5;
            font-size: 14px;
            min-height: 48px;
            max-height: 140px;
            overflow-y: auto;
            background: #f8fafc;
        }

        .reply-hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
            padding-left: 4px;
        }

        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #0f172a;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 8px 12px;
            margin: 0 16px 0 16px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
            width: fit-content;
        }

        .typing-indicator::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #25d366;
            animation: blink-dot 1.2s infinite ease-in-out;
        }

        @keyframes blink-dot {
            0%, 100% { opacity: 0.25; }
            50% { opacity: 1; }
        }

        .bridge-reply .btn-send {
            width: 52px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0;
            border: none;
            background: #25d366;
            color: #fff;
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.18);
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .bridge-reply .btn-send:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        .bridge-reply .btn-send:disabled {
            background: #94d3a2;
            cursor: not-allowed;
            box-shadow: none;
        }

        .bridge-reply .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .msg-box {
            height: 58vh;
            overflow-y: auto;
            padding: 22px 18px;
            background: #e5ddd5;
            border-radius: 0 0 10px 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .msg-box::-webkit-scrollbar {
            width: 8px;
        }

        .msg-box::-webkit-scrollbar-thumb {
            background: rgba(75, 85, 99, 0.35);
            border-radius: 999px;
        }

        .msg-row {
            margin-bottom: 0;
            display: flex;
            width: 100%;
        }

        .msg-row.user {
            justify-content: flex-start;
        }

        .msg-row.admin,
        .msg-row.bot {
            justify-content: flex-end;
        }

        .msg-pill {
            max-width: 72%;
            border-radius: 18px;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.5;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .msg-row.user .msg-pill {
            border-radius: 18px 18px 18px 4px;
            background: #ffffff;
            color: #111827;
        }

        .msg-row.admin .msg-pill,
        .msg-row.bot .msg-pill {
            background: #25d366;
            color: #fff;
            border-radius: 18px 18px 4px 18px;
        }

        .msg-row.user .msg-pill::after {
            content: '';
            position: absolute;
            left: -8px;
            bottom: 2px;
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50% 0 50% 50%;
        }

        .msg-row.admin .msg-pill::after,
        .msg-row.bot .msg-pill::after {
            content: '';
            position: absolute;
            right: -8px;
            bottom: 2px;
            width: 16px;
            height: 16px;
            background: #25d366;
            border-radius: 0 50% 50% 50%;
        }

        .msg-meta {
            margin-top: 8px;
            font-size: 12px;
            opacity: 0.75;
            color: inherit;
        }

        @media (max-width: 991px) {
            .bridge-wrap {
                grid-template-columns: 1fr;
            }

            .msg-box {
                height: 50vh;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm chat-back-btn d-none" id="backToListBtn" type="button">Back to List</button>
            <h4 class="mb-0">Web Chat Bridge</h4>
        </div>
        <a href="{{ route('messages.index') }}" class="btn btn-outline-secondary btn-sm">Back to Messages</a>
    </div>

    <div class="bridge-wrap">
        <div class="bridge-card">
            <div class="bridge-head">Conversations</div>
            <div id="conversationList" class="conv-list"></div>
        </div>

        <div class="bridge-card">
            <div class="bridge-head" id="conversationTitle">Select a conversation</div>
            <div id="typingIndicator" class="typing-indicator d-none">Typing...</div>
            <div id="messageBox" class="msg-box">
                <div class="text-muted">No conversation selected.</div>
            </div>
            <div class="bridge-reply">
                <form id="replyForm" class="d-flex w-100 align-items-center gap-2 mb-0">
                    <div class="form-group flex-grow-1 mb-0">
                        <textarea id="replyInput" class="form-control" rows="1" placeholder="Type a message..." disabled></textarea>
                        <div class="reply-hint">Press Enter to send, Shift+Enter for a new line.</div>
                    </div>
                    <button type="submit" class="btn btn-success btn-send" id="replyBtn" disabled style="margin-top:-20px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-send-fill" viewBox="0 0 16 16">
                            <path d="M15.964 0.686a.5.5 0 0 0-.527-.094L.733 6.77a.5.5 0 0 0 .005.938l5.857 2.207 2.207 5.857a.5.5 0 0 0 .938.005l6.178-14.704a.5.5 0 0 0-.114-.967z"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>

        const urls = {
            conversations: "{{ route('web-chat-new.conversations') }}",
            history: "{{ route('web-chat-new.history') }}",
            stream: "{{ route('web-chat-new.stream') }}",
            reply: "{{ route('web-chat-new.reply') }}",
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        const bridgeWrap = document.querySelector('.bridge-wrap')
        const backBtn = document.getElementById('backToListBtn')
        const conversationList = document.getElementById('conversationList')
        const messageBox = document.getElementById('messageBox')
        const title = document.getElementById('conversationTitle')
        const replyForm = document.getElementById('replyForm')
        const replyInput = document.getElementById('replyInput')
        const replyBtn = document.getElementById('replyBtn')

        let activeConversationId = ''
        let loadingConversations = false
        let loadingHistory = false
        let eventSource = null
        let lastMessageId = 0

        function escapeHtml(value) {
            const div = document.createElement('div')
            div.textContent = value || ''
            return div.innerHTML
        }

        function formatTimestamp(value) {
            if (!value) {
                return ''
            }
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) {
                return value
            }
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        }

        function showTypingIndicator(text = 'Typing...') {
            const indicator = document.getElementById('typingIndicator')
            if (!indicator) {
                return
            }
            indicator.textContent = text
            indicator.classList.remove('d-none')
        }

        function hideTypingIndicator(delay = 1200) {
            const indicator = document.getElementById('typingIndicator')
            if (!indicator) {
                return
            }
            window.clearTimeout(indicator.hideTimeout)
            indicator.hideTimeout = window.setTimeout(() => {
                indicator.classList.add('d-none')
            }, delay)
        }

        function autoResizeInput() {
            if (!replyInput) {
                return
            }
            replyInput.style.height = 'auto'
            replyInput.style.height = `${Math.min(replyInput.scrollHeight, 180)}px`
        }

        function handleReplyKeydown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault()
                replyForm.dispatchEvent(new Event('submit', { cancelable: true }))
            }
        }

        if (replyInput) {
            replyInput.addEventListener('input', () => {
                autoResizeInput()
                if (replyInput.value.trim()) {
                    showTypingIndicator('Drafting reply...')
                    hideTypingIndicator(900)
                }
            })
            replyInput.addEventListener('keydown', handleReplyKeydown)
        }

        async function loadConversations() {
            if (loadingConversations) {
                return
            }

            loadingConversations = true

            try {
                const res = await fetch(urls.conversations)
                const payload = await res.json()
                const list = Array.isArray(payload.conversations) ? payload.conversations : []

                if (!list.length) {
                    conversationList.innerHTML = '<div class="p-3 text-muted">No conversations yet.</div>'
                    return
                }

                conversationList.innerHTML = list.map((item) => {
                    const id = item.conversation_id || item.external_id || ''
                    const preview = item.last_message || item.preview || 'No messages yet'
                    const when = item.last_message_at || item.updated_at || ''

                    const avatar = escapeHtml((item.conversation_id || item.external_id || 'C').charAt(0))
                const channel = escapeHtml(item.channel || 'web')

                return `<div class="conv-item ${id === activeConversationId ? 'active' : ''}" data-id="${escapeHtml(id)}">
                        <div class="conv-avatar">${avatar}</div>
                        <div class="conv-body">
                            <div class="conv-header">
                                <div class="conv-id">${escapeHtml(id)}</div>
                                <div class="conv-time">${escapeHtml(when)}</div>
                            </div>
                            <div class="conv-preview">${escapeHtml(preview)}</div>
                        </div>
                        <div class="conv-badge">${channel}</div>
                    </div>`
                }).join('')
            } catch (error) {
                conversationList.innerHTML = '<div class="p-3 text-danger">Failed to load conversations.</div>'
            } finally {
                loadingConversations = false
            }
        }

        async function loadHistory(conversationId, forceScrollBottom = false) {
            if (!conversationId) {
                return
            }

            if (loadingHistory) {
                return
            }

            loadingHistory = true

            activeConversationId = conversationId
            title.textContent = `Conversation: ${conversationId}`
            replyInput.disabled = false
            replyBtn.disabled = false
            autoResizeInput()
            expandChatWindow()

            const isNearBottom =
                messageBox.scrollHeight - messageBox.scrollTop - messageBox.clientHeight < 120

            try {
                const res = await fetch(`${urls.history}?conversation_id=${encodeURIComponent(conversationId)}`)
                const payload = await res.json()
                const msgs = Array.isArray(payload.messages) ? payload.messages : []

                if (!msgs.length) {
                    messageBox.innerHTML = '<div class="text-muted">No messages yet.</div>'
                } else {
                    lastMessageId = msgs.reduce((maxId, msg) => {
                        const value = Number(msg.id)
                        return Number.isFinite(value) ? Math.max(maxId, value) : maxId
                    }, lastMessageId)

                    messageBox.innerHTML = msgs.map((msg) => {
                        const role = (msg.role || 'user').toLowerCase()
                        const text = msg.text || msg.message || ''
                        const when = msg.created_at || ''

                        return `<div class="msg-row ${escapeHtml(role)}">
                            <div class="msg-pill">
                                <div>${escapeHtml(text)}</div>
                                <div class="msg-meta">${escapeHtml(role)} ${when ? '• ' + escapeHtml(formatTimestamp(when)) : ''}</div>
                            </div>
                        </div>`
                    }).join('')
                }

                if (forceScrollBottom || isNearBottom) {
                    messageBox.scrollTop = messageBox.scrollHeight
                }
                await loadConversations()
            } catch (error) {
                messageBox.innerHTML = '<div class="text-danger">Failed to load messages.</div>'
            } finally {
                loadingHistory = false
            }
        }

        conversationList.addEventListener('click', async (event) => {
            const item = event.target.closest('.conv-item')
            if (!item) {
                return
            }

            await loadHistory(item.dataset.id, true)
        })

        backBtn.addEventListener('click', collapseChatWindow)

        replyForm.addEventListener('submit', async (event) => {
            event.preventDefault()

            const message = replyInput.value.trim()
            if (!activeConversationId || !message) {
                return
            }

            replyBtn.disabled = true

            try {
                const res = await fetch(urls.reply, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        conversation_id: activeConversationId,
                        message: message,
                    }),
                })

                const payload = await res.json()
                if (!res.ok || payload.status === 'error') {
                    throw new Error(payload.message || 'Failed to send reply')
                }

                replyInput.value = ''
                autoResizeInput()
                await loadHistory(activeConversationId, true)
                replyInput.focus()
            } catch (error) {
                alert(error.message || 'Unable to send reply')
            } finally {
                replyBtn.disabled = false
            }
        })

        function expandChatWindow() {
            if (bridgeWrap) {
                bridgeWrap.classList.add('expanded')
            }
            if (backBtn) {
                backBtn.classList.remove('d-none')
            }
        }

        function collapseChatWindow() {
            if (bridgeWrap) {
                bridgeWrap.classList.remove('expanded')
            }
            if (backBtn) {
                backBtn.classList.add('d-none')
            }
        }

        function startLiveStream() {
            if (eventSource) {
                eventSource.close()
            }

            eventSource = new EventSource(`${urls.stream}?last_id=${encodeURIComponent(lastMessageId)}`)

            eventSource.addEventListener('message', async (event) => {
                try {
                    const payload = JSON.parse(event.data)
                    const messageId = Number(payload?.id)

                    if (Number.isFinite(messageId)) {
                        lastMessageId = Math.max(lastMessageId, messageId)
                    }

                    await loadConversations()

                    if (activeConversationId && payload?.conversation_id === activeConversationId) {
                        const role = (payload?.role || 'user').toLowerCase()
                        if (role === 'admin' || role === 'bot') {
                            showTypingIndicator('New message...')
                            hideTypingIndicator(900)
                        }
                        await loadHistory(activeConversationId)
                    }
                } catch (error) {
                    // Ignore malformed stream payloads.
                }
            })

            eventSource.onerror = () => {
                if (eventSource) {
                    eventSource.close()
                }

                window.setTimeout(startLiveStream, 1200)
            }
        }

        loadConversations()
        startLiveStream()
    </script>
</body>
</html>
