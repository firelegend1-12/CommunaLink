-- Add Cancelled status for document request cancellation workflow
ALTER TABLE document_requests
MODIFY status ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled')
NOT NULL DEFAULT 'Pending';
