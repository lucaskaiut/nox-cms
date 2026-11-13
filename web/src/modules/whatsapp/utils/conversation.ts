import type { Conversation } from '../services/whatsapp.service'

export function conversationTitle(conversation: Conversation): string {
  return conversation.lead?.name || conversation.contact_name || conversation.phone
}
