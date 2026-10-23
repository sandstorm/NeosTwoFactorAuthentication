// Reads the mails caught by the SUT's Mailpit via its HTTP API (see sut-base-docker-compose.yaml).
const MAILPIT_URL = process.env.MAILPIT_URL || 'http://localhost:8025';

export type Mail = { id: string; to: string[]; subject: string; text: string };

export async function deleteAllMails() {
  await fetch(`${MAILPIT_URL}/api/v1/messages`, { method: 'DELETE' });
}

export async function mailsTo(recipient: string): Promise<Mail[]> {
  const response = await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:"${recipient}"`)}`);
  const result = (await response.json()) as { messages: { ID: string; To: { Address: string }[]; Subject: string }[] };
  return Promise.all(
    result.messages.map(async (message) => {
      const detail = (await (await fetch(`${MAILPIT_URL}/api/v1/message/${message.ID}`)).json()) as { Text: string };
      return { id: message.ID, to: message.To.map((to) => to.Address), subject: message.Subject, text: detail.Text };
    }),
  );
}

// Mails are sent synchronously during the request, but give the SMTP hop a moment.
export async function waitForMailsTo(recipient: string, count: number): Promise<Mail[]> {
  for (let attempt = 0; attempt < 20; attempt++) {
    const mails = await mailsTo(recipient);
    if (mails.length >= count) {
      return mails;
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  return mailsTo(recipient);
}

/**
 * Links in the plain text part. With several accounts sharing one email, each link is preceded by a
 * "... username X:" line - pass the username to get only that account's link.
 */
export function linksInMail(mail: Mail, pathPart: string, username?: string): string[] {
  const lines = mail.text.split('\n').map((line) => line.trim());
  const linkIndexes = lines.flatMap((line, index) => (line.includes(pathPart) ? [index] : []));
  if (username === undefined || linkIndexes.length < 2) {
    return linkIndexes.map((index) => lines[index] ?? '');
  }
  return linkIndexes
    .filter((index) => (lines[index - 1] ?? '').includes(username))
    .map((index) => lines[index] ?? '');
}

export async function firstLinkInMailTo(recipient: string, pathPart: string, username?: string): Promise<string> {
  const mails = await waitForMailsTo(recipient, 1);
  const link = mails.flatMap((mail) => linksInMail(mail, pathPart, username))[0];
  if (link === undefined) {
    throw new Error(`No link containing "${pathPart}" in the mails to ${recipient}`);
  }
  return link;
}
