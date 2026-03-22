-- Normalize legacy walk-in requests that were inserted directly as Processing
-- This only changes walk-ins that have no requester user and no remarks yet.
UPDATE document_requests
SET status = 'Pending'
WHERE requested_by_user_id IS NULL
  AND status = 'Processing'
  AND (remarks IS NULL OR remarks = '');
