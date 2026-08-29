import { requestJson } from './apiClient';

export type ContactSettings = {
  business_name: string;
  opening_year?: string;
  address: string;
  phone: string;
  reception_contact_number: string;
  whatsapp_reservation_number: string;
  email: string;
  business_hours: string;
  business_hours_mode: '24_7' | 'custom';
  business_hours_schedule: string;
  facebook_link: string;
  instagram_link: string;
  map_embed_url: string;
};

export type BusinessDayHours = {
  enabled: boolean;
  all_day: boolean;
  open: string;
  close: string;
};

export type ContactFormPayload = {
  name: string;
  email: string;
  phone?: string;
  subject?: string;
  message: string;
};

export async function getPublicContactSettings(): Promise<ContactSettings> {
  return requestJson<ContactSettings>('/settings/get-contact.php');
}

export async function submitContactMessage(payload: ContactFormPayload): Promise<{ inquiry_id?: string }> {
  return requestJson<{ inquiry_id?: string }>('/contact/submit_contact.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}
