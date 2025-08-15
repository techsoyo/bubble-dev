/**
 * Email Service Mock
 * 
 * This file simulates email sending functionality that would be implemented
 * with PHPMailer in production. For this MVP, we're creating a mock implementation.
 */

interface EmailOptions {
  to: string;
  subject: string;
  body: string;
  isHtml?: boolean;
}

/**
 * Sends an email notification (mock implementation for MVP)
 * In production, this would integrate with PHPMailer on the backend
 */
export const sendEmail = async (options: EmailOptions): Promise<{success: boolean, message: string}> => {
  // In production, this would be an API call to a PHP backend that uses PHPMailer
  console.log('📧 MOCK EMAIL SERVICE');
  console.log('---------------------');
  console.log(`To: ${options.to}`);
  console.log(`Subject: ${options.subject}`);
  console.log('Body:');
  console.log(options.body);
  console.log('---------------------');
  
  // Simulate network delay
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Simulate success (or random failures to test error handling)
  const success = Math.random() > 0.1; // 10% failure rate for testing
  
  // Log to localStorage for history/demo purposes
  const emailHistory = JSON.parse(localStorage.getItem('emailHistory') || '[]');
  emailHistory.push({
    timestamp: new Date().toISOString(),
    ...options,
    success
  });
  localStorage.setItem('emailHistory', JSON.stringify(emailHistory));
  
  if (success) {
    return {
      success: true,
      message: 'Email sent successfully'
    };
  } else {
    return {
      success: false,
      message: 'Failed to send email. Server error.'
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
): Promise<{success: boolean, message: string}> => {
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
): Promise<{success: boolean, message: string}> => {
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
 * Gets the email history from localStorage
 * This is for demo purposes in the MVP - in production this would come from a database
 */
export const getEmailHistory = (): Array<{
  timestamp: string;
  to: string;
  subject: string;
  body: string;
  success: boolean;
}> => {
  return JSON.parse(localStorage.getItem('emailHistory') || '[]');
};

/**
 * Clear the email history from localStorage
 * This is for demo purposes in the MVP
 */
export const clearEmailHistory = (): void => {
  localStorage.removeItem('emailHistory');
};