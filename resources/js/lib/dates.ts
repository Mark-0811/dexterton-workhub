import dayjs from 'dayjs';

export function formatDate(value?: string | null): string {
  if (!value) return 'No date set';

  const parsed = dayjs(value);
  return parsed.isValid() ? parsed.format('MMM D, YYYY') : 'No date set';
}

export function formatDateTime(value?: string | null): string {
  if (!value) return 'No due date';

  const parsed = dayjs(value);
  return parsed.isValid() ? parsed.format('MMM D, YYYY • h:mm A') : 'No due date';
}

export function toDateTimeLocal(value?: string | null): string {
  if (!value) return '';

  const parsed = dayjs(value);
  return parsed.isValid() ? parsed.format('YYYY-MM-DDTHH:mm') : '';
}
