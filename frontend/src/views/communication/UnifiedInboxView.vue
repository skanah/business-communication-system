<template>
  <div class="unified-inbox">
    <div class="inbox-header">
      <h2>Unified Inbox</h2>
      <div class="inbox-stats">
        <div class="stat-item">
          <span class="stat-label">Total Messages</span>
          <span class="stat-value">{{ stats.total_messages || 0 }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">Unread</span>
          <span class="stat-value">{{ stats.unread_messages || 0 }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">Today</span>
          <span class="stat-value">{{ stats.messages_today || 0 }}</span>
        </div>
      </div>
    </div>

    <div class="inbox-controls">
      <div class="search-section">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search messages..."
          class="search-input"
          @input="debouncedSearch"
        />
      </div>

      <div class="filter-section">
        <select v-model="filters.message_type" @change="() => loadMessages()" class="filter-select">
          <option value="">All Types</option>
          <option value="incoming">Incoming</option>
          <option value="outgoing">Outgoing</option>
        </select>

        <select v-model="filters.channel" @change="() => loadMessages()" class="filter-select">
          <option value="">All Channels</option>
          <option value="sms">SMS</option>
          <option value="email">Email</option>
        </select>

        <select v-model="filters.message_status" @change="() => loadMessages()" class="filter-select">
          <option value="">All Status</option>
          <option value="sent">Sent</option>
          <option value="delivered">Delivered</option>
          <option value="read">Read</option>
          <option value="failed">Failed</option>
        </select>

        <button @click="clearFilters" class="clear-filters-btn">Clear Filters</button>
      </div>
    </div>

    <div class="messages-container">
      <div v-if="loading" class="loading-spinner">
        <div class="spinner"></div>
        Loading messages...
      </div>

      <div v-else-if="messages.length === 0" class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>No messages found</h3>
        <p>Try adjusting your filters or check back later.</p>
      </div>

      <div v-else class="messages-list">
        <div
          v-for="message in messages"
          :key="message.id"
          :class="['message-item', { unread: message.message_status === 'received' }]"
          @click="selectMessage(message)"
        >
          <div class="message-header">
            <div class="message-meta">
              <span :class="['channel-badge', message.type]">{{ message.channel }}</span>
              <span class="message-type">{{ message.message_type }}</span>
              <span :class="['status-badge', message.message_status]">
                {{ message.message_status }}
              </span>
            </div>
            <div class="message-time">
              {{ formatDate(message.created_at) }}
            </div>
          </div>

          <div class="message-sender">
            <strong>{{ message.sender }}</strong>
            <span v-if="message.message_type === 'outgoing'" class="arrow">→</span>
            <span v-else class="arrow">←</span>
            {{ message.receiver }}
          </div>

          <div class="message-subject">
            {{ message.subject }}
          </div>

          <div class="message-preview">
            {{ truncateContent(message.content, 100) }}
          </div>

          <div class="message-footer">
            <div class="message-info">
              <span v-if="message.has_attachments" class="attachment-indicator">📎</span>
              <span class="cost-info">${{ message.cost }}</span>
            </div>
            <div class="message-actions">
              <button
                @click.stop="markAsRead(message.id)"
                v-if="message.message_status === 'received'"
                class="action-btn"
              >
                Mark Read
              </button>
              <button @click.stop="deleteMessage(message.id)" class="action-btn delete">
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination">
        <button
          @click="changePage(pagination.current_page - 1)"
          :disabled="pagination.current_page === 1"
          class="page-btn"
        >
          Previous
        </button>

        <span class="page-info">
          Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{
            pagination.total
          }}
          total messages)
        </span>

        <button
          @click="changePage(pagination.current_page + 1)"
          :disabled="pagination.current_page === pagination.last_page"
          class="page-btn"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Message Detail Modal -->
    <div v-if="selectedMessage" class="message-modal" @click="closeMessageModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>{{ selectedMessage.subject }}</h3>
          <button @click="closeMessageModal" class="close-btn">&times;</button>
        </div>

        <div class="modal-meta">
          <div class="meta-item"><strong>From:</strong> {{ selectedMessage.sender }}</div>
          <div class="meta-item"><strong>To:</strong> {{ selectedMessage.receiver }}</div>
          <div class="meta-item"><strong>Channel:</strong> {{ selectedMessage.channel }}</div>
          <div class="meta-item"><strong>Status:</strong> {{ selectedMessage.message_status }}</div>
          <div class="meta-item">
            <strong>Date:</strong> {{ formatDate(selectedMessage.created_at) }}
          </div>
          <div v-if="selectedMessage.cost > 0" class="meta-item">
            <strong>Cost:</strong> ${{ selectedMessage.cost }}
          </div>
        </div>

        <div class="modal-body">
          <div
            v-if="selectedMessage.html_content"
            v-html="selectedMessage.html_content"
            class="html-content"
          ></div>
          <div v-else class="text-content">{{ selectedMessage.content }}</div>

          <div
            v-if="selectedMessage.attachments && selectedMessage.attachments.length > 0"
            class="attachments"
          >
            <h4>Attachments:</h4>
            <ul>
              <li v-for="attachment in selectedMessage.attachments" :key="attachment">
                {{ attachment }}
              </li>
            </ul>
          </div>
        </div>

        <div class="modal-actions">
          <button
            @click="markAsRead(selectedMessage.id)"
            v-if="selectedMessage.message_status === 'received'"
            class="action-btn"
          >
            Mark as Read
          </button>
          <button @click="deleteMessage(selectedMessage.id)" class="action-btn delete">
            Delete Message
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiService from '@/services/apiService'

interface Message {
  id: number
  type: 'sms' | 'email'
  channel: string
  message_type: 'incoming' | 'outgoing'
  message_status: string
  sender: string
  receiver: string
  subject: string
  content: string
  html_content?: string
  cost: number
  currency: string
  has_attachments: boolean
  attachments?: string[]
  created_at: string
  updated_at: string
}

interface Stats {
  total_messages: number
  unread_messages: number
  messages_today: number
  sms_messages: {
    total: number
    incoming: number
    outgoing: number
    total_cost: number
  }
  email_messages: {
    total: number
    incoming: number
    outgoing: number
    total_cost: number
    with_attachments: number
  }
  failed_messages: number
  total_cost: number
}

interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

const messages = ref<Message[]>([])
const stats = ref<Stats>({} as Stats)
const pagination = ref<Pagination | null>(null)
const selectedMessage = ref<Message | null>(null)
const loading = ref(false)

const filters = ref({
  search: '',
  message_type: '',
  channel: '',
  message_status: '',
  sort_by: 'created_at',
  sort_order: 'desc',
})

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadMessages()
  }, 500)
}

interface InboxMessagesResponse {
  success: boolean
  message: string
  data: {
    messages: Message[]
    pagination: Pagination
  }
}

interface InboxStatsResponse {
  success: boolean
  message: string
  data: Stats
}

const loadMessages = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: page.toString(),
      limit: '20',
      ...Object.fromEntries(Object.entries(filters.value).filter(([_, value]) => value !== '')),
    })

    const response = await apiService.get<InboxMessagesResponse>(`/inbox/messages?${params}`)
    messages.value = response.data.data.messages
    pagination.value = response.data.data.pagination
  } catch (error) {
    console.error('Failed to load messages:', error)
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const response = await apiService.get<InboxStatsResponse>('/inbox/stats')
    stats.value = response.data.data
  } catch (error) {
    console.error('Failed to load stats:', error)
  }
}

const selectMessage = (message: Message) => {
  selectedMessage.value = message
}

const closeMessageModal = () => {
  selectedMessage.value = null
}

const markAsRead = async (messageId: number) => {
  try {
    await apiService.put(`/inbox/messages/${messageId}/read`)
    await loadMessages(pagination.value?.current_page || 1)
    await loadStats()
    if (selectedMessage.value?.id === messageId) {
      selectedMessage.value.message_status = 'read'
    }
  } catch (error) {
    console.error('Failed to mark message as read:', error)
  }
}

const deleteMessage = async (messageId: number) => {
  if (!confirm('Are you sure you want to delete this message?')) return

  try {
    await apiService.delete(`/inbox/messages/${messageId}`)
    await loadMessages(pagination.value?.current_page || 1)
    await loadStats()
    if (selectedMessage.value?.id === messageId) {
      selectedMessage.value = null
    }
  } catch (error) {
    console.error('Failed to delete message:', error)
  }
}

const changePage = (page: number) => {
  loadMessages(page)
}

const clearFilters = () => {
  filters.value = {
    search: '',
    message_type: '',
    channel: '',
    message_status: '',
    sort_by: 'created_at',
    sort_order: 'desc',
  }
  loadMessages()
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString()
}

const truncateContent = (content: string, maxLength: number) => {
  if (content.length <= maxLength) return content
  return content.substring(0, maxLength) + '...'
}

onMounted(() => {
  loadMessages()
  loadStats()
})
</script>

<style scoped>
.unified-inbox {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.inbox-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.inbox-header h2 {
  margin: 0;
  color: #1f2937;
}

.inbox-stats {
  display: flex;
  gap: 20px;
}

.stat-item {
  text-align: center;
}

.stat-label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.stat-value {
  display: block;
  font-size: 24px;
  font-weight: bold;
  color: #1f2937;
}

.inbox-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  gap: 20px;
}

.search-section {
  flex: 1;
  max-width: 400px;
}

.search-input {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.filter-section {
  display: flex;
  gap: 10px;
  align-items: center;
}

.filter-select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
}

.clear-filters-btn {
  padding: 8px 16px;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}

.clear-filters-btn:hover {
  background: #e5e7eb;
}

.messages-container {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #6b7280;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e5e7eb;
  border-top: 4px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #6b7280;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.messages-list {
  max-height: 600px;
  overflow-y: auto;
}

.message-item {
  padding: 16px 20px;
  border-bottom: 1px solid #f3f4f6;
  cursor: pointer;
  transition: background-color 0.2s;
}

.message-item:hover {
  background-color: #f9fafb;
}

.message-item.unread {
  background-color: #eff6ff;
  border-left: 4px solid #3b82f6;
}

.message-item:last-child {
  border-bottom: none;
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.message-meta {
  display: flex;
  gap: 8px;
  align-items: center;
}

.channel-badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: uppercase;
}

.channel-badge.sms {
  background: #dbeafe;
  color: #1e40af;
}

.channel-badge.email {
  background: #dcfce7;
  color: #166534;
}

.message-type {
  font-size: 12px;
  color: #6b7280;
  text-transform: capitalize;
}

.status-badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.sent {
  background: #dbeafe;
  color: #1e40af;
}
.status-badge.delivered {
  background: #dcfce7;
  color: #166534;
}
.status-badge.read {
  background: #f3f4f6;
  color: #374151;
}
.status-badge.failed {
  background: #fee2e2;
  color: #dc2626;
}
.status-badge.received {
  background: #fef3c7;
  color: #d97706;
}

.message-time {
  font-size: 12px;
  color: #6b7280;
}

.message-sender {
  margin-bottom: 4px;
  font-size: 14px;
  color: #374151;
}

.arrow {
  margin: 0 8px;
  color: #6b7280;
}

.message-subject {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 4px;
}

.message-preview {
  color: #6b7280;
  font-size: 14px;
  line-height: 1.4;
}

.message-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
}

.message-info {
  display: flex;
  gap: 12px;
  align-items: center;
}

.attachment-indicator {
  font-size: 16px;
}

.cost-info {
  font-size: 12px;
  color: #6b7280;
}

.message-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  padding: 4px 8px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: white;
  cursor: pointer;
  font-size: 12px;
  transition: background-color 0.2s;
}

.action-btn:hover {
  background: #f3f4f6;
}

.action-btn.delete {
  color: #dc2626;
  border-color: #fecaca;
}

.action-btn.delete:hover {
  background: #fef2f2;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  padding: 20px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

.page-btn {
  padding: 8px 16px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: white;
  cursor: pointer;
  font-size: 14px;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-btn:hover:not(:disabled) {
  background: #f3f4f6;
}

.page-info {
  font-size: 14px;
  color: #6b7280;
}

/* Modal Styles */
.message-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 8px;
  max-width: 800px;
  max-height: 80vh;
  width: 90%;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
  color: #1f2937;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6b7280;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-meta {
  padding: 20px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.meta-item {
  margin-bottom: 8px;
  font-size: 14px;
}

.meta-item:last-child {
  margin-bottom: 0;
}

.modal-body {
  padding: 20px;
  max-height: 400px;
  overflow-y: auto;
}

.html-content {
  line-height: 1.6;
}

.html-content img {
  max-width: 100%;
  height: auto;
}

.text-content {
  white-space: pre-wrap;
  line-height: 1.6;
}

.attachments {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

.attachments h4 {
  margin: 0 0 10px 0;
  color: #1f2937;
}

.attachments ul {
  margin: 0;
  padding-left: 20px;
}

.attachments li {
  margin-bottom: 4px;
}

.modal-actions {
  padding: 20px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 768px) {
  .inbox-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .inbox-controls {
    flex-direction: column;
    align-items: stretch;
    gap: 15px;
  }

  .filter-section {
    flex-wrap: wrap;
  }

  .message-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .message-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .pagination {
    flex-direction: column;
    gap: 10px;
  }
}
</style>
