/**
 * Email Service - Production Implementation
 * 
 * This service integrates with the backend email endpoint that uses PHPMailer.
 * Replaces the previous mock implementation for production use.
 */

import { safeGet, safeSet, safeRemove } from '../utils/safeStorage';

interface EmailOptions {
  to: string;
  subject: string;
  body: string;
  isHtml?: boolean;
}

/**
 * Sends an email using the backend PHPMailer implementation
 */
export const sendEmail = async (options: EmailOptions): Promise<{ success: boolean, message: string }> => {
  try {
    const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost/bubble_of_talents_1.0/backend/public';

    const response = await fetch(`${apiUrl}/api/send-email`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify(options)
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    return {
      success: result.success,
      message: result.message || (result.success ? 'Email sent successfully' : 'Failed to send email')
    };

  } catch (error) {
    console.error('Error sending email:', error);
    return {
      success: false,
      message: error instanceof Error ? error.message : 'Failed to send email. Network error.'
    };
  }
};

/**
 * Sends a notification to a recruiter about a new candidate assignment
 */
export const sendRecruiterAssignmentNotification = async (
  recruiterEmail: string,
  recruiterName: string,
  candidateName: string,
  candidateEmail: string,
  departmentName: string
): Promise<{ success: boolean, message: string }> => {
  const subject = `New Candidate Assignment - ${candidateName}`;

  // Create HTML email body
  const htmlBody = `
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
      <div style="background-color: #FF4785; padding: 20px; text-align: center;">
        <h1 style="color: white; margin: 0;">New Candidate Assignment</h1>
      </div>
      
      <div style="padding: 20px; border: 1px solid #ddd; border-top: none;">
        <p>Hello <strong>${recruiterName}</strong>,</p>
        
        <p>You have been assigned a new candidate to review from the HR department:</p>
        
        <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0;">
          <p><strong>Candidate:</strong> ${candidateName}</p>
          <p><strong>Email:</strong> ${candidateEmail}</p>
          <p><strong>Department:</strong> ${departmentName}</p>
        </div>
        
        <p>Please log in to the recruitment system to begin the recruitment process for this candidate.</p>
        
        <div style="text-align: center; margin: 25px 0;">
          <a href="http://localhost:5174/dashboard/recruiter" style="background-color: #FF4785; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
            Go to Recruiter Dashboard
          </a>
        </div>
        
        <p>If you have any questions, please contact the HR department.</p>
        
        <p>Best regards,<br>Bubblegum Recruitment Team</p>
      </div>
      
      <div style="background-color: #333; color: white; padding: 10px; text-align: center; font-size: 12px;">
        <p>This is an automated message. Please do not reply to this email.</p>
      </div>
    </div>
  `;

  return sendEmail({
    to: recruiterEmail,
    subject,
    body: htmlBody,
    isHtml: true
  });
};

/**
 * Sends a notification to a candidate about their recruitment process status update
 */
export const sendCandidateStatusUpdateNotification = async (
  candidateEmail: string,
  candidateName: string,
  previousStatus: string,
  newStatus: string,
  jobTitle: string,
  recruiterName: string,
  recruiterEmail: string,
  notes?: string
): Promise<{ success: boolean, message: string }> => {
  const subject = `Your Application Status Update - ${jobTitle}`;

  // Define status-specific messages
  const statusMessages: Record<string, string> = {
    'Received': 'We have received your application and will be reviewing it shortly.',
    'Reviewing': 'Your application is currently being reviewed by our recruitment team.',
    'Interview': 'Congratulations! We would like to invite you for an interview.',
    'Shortlisted': 'Congratulations! You have been shortlisted for the next round of the recruitment process.',
    'Offer': 'We are pleased to inform you that we would like to extend you an offer.',
    'Hired': 'Congratulations! You have been hired for the position.',
    'Rejected': 'We appreciate your interest in our company, but we have decided to proceed with other candidates.'
  };

  // Get appropriate message based on status, or use a default message
  const statusMessage = statusMessages[newStatus] ||
    `Your application status has been updated from ${previousStatus} to ${newStatus}.`;

  // Create HTML email body
  const htmlBody = `
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
      <div style="background-color: #FF4785; padding: 20px; text-align: center;">
        <h1 style="color: white; margin: 0;">Application Status Update</h1>
      </div>
      
      <div style="padding: 20px; border: 1px solid #ddd; border-top: none;">
        <p>Hello <strong>${candidateName}</strong>,</p>
        
        <p>There has been an update to your application for the <strong>${jobTitle}</strong> position.</p>
        
        <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0;">
          <p><strong>Previous Status:</strong> ${previousStatus}</p>
          <p><strong>New Status:</strong> <span style="font-weight: bold; color: ${newStatus === 'Rejected' ? '#FF4785' : '#4CAF50'}">${newStatus}</span></p>
        </div>
        
        <p>${statusMessage}</p>
        
        ${notes ? `<p><strong>Additional Notes:</strong></p><p>${notes}</p>` : ''}
        
        ${newStatus === 'Interview' ? `
          <div style="background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <p><strong>Next Steps:</strong></p>
            <p>Our recruiter will be contacting you shortly to schedule an interview. Please ensure your contact details are up to date.</p>
          </div>
        ` : ''}
        
        <p>If you have any questions about your application or the recruitment process, please don't hesitate to contact your recruiter:</p>
        
        <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0;">
          <p><strong>Recruiter:</strong> ${recruiterName}</p>
          <p><strong>Email:</strong> <a href="mailto:${recruiterEmail}">${recruiterEmail}</a></p>
        </div>
        
        <p>Thank you for your interest in our company.</p>
        
        <p>Best regards,<br>Bubblegum Recruitment Team</p>
      </div>
      
      <div style="background-color: #333; color: white; padding: 10px; text-align: center; font-size: 12px;">
        <p>This is an automated message. Please do not reply to this email.</p>
      </div>
    </div>
  `;

  return sendEmail({
    to: candidateEmail,
    subject,
    body: htmlBody,
    isHtml: true
  });
};

/**
 * Gets the email history from safe storage (no-op en producción)
 * This is for demo purposes in the MVP - in production this would come from a database
 */
export const getEmailHistory = (): Array<{
  timestamp: string;
  to: string;
  subject: string;
  body: string;
  success: boolean;
}> => {
  return safeGet<any[]>('emailHistory', []);
};

/**
 * Clear the email history from safe storage (no-op en producción)
 * This is for demo purposes in the MVP
 */
export const clearEmailHistory = (): void => {
  safeRemove('emailHistory');
};
