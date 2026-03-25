# Permit Expiry Checker - Quick Start for Admins

## What You'll See on the Dashboard

### New Permit Status Card
Located in the Quick Stats section at the top of your dashboard:

```
┌─────────────────────────────┐
│  Permit Status        [↻]  │
│                             │
│  🎖️  X Expiring Soon        │
│                             │
│  Status: [Orange or Red]    │
│     3 Expiring Soon         │
└─────────────────────────────┘
```

**If Permits Are Normal (Green/Orange):**
- Shows count of permits expiring within 30 days
- Allows you to plan renewals

**If Permits Are Expired (Red):**
- Shows urgent count
- Emphasizes immediate action needed
- Display reads: "⚠️ X Expired!"

---

## How to Use It - 3 Simple Steps

### Step 1: Check Permit Status
🎯 **Location:** Top of Admin Dashboard  
When you log in, you'll immediately see the Permit Status card showing:
- Number of permits expiring soon
- Color indicator (orange = warning, red = urgent)

### Step 2: Refresh (Optional)
🔄 **Action:** Click the refresh button (↻) on the card  
This manually triggers an immediate check:
- Button spins while checking
- Card updates with latest data
- You'll see all expiration alerts
- Notifications are sent to all admins

### Step 3: Take Action
📋 **Navigate:** Click "Permits" in the Quick Actions section  
This takes you to the monitoring page where you can:
- View all expiring permits
- Process renewals
- Update permit information
- View permit details

---

## Automatic Features (No Action Needed)

### What Happens Automatically:

✅ **On Page Load**
- System checks all permits automatically
- Updates the permit status card
- No manual action required

✅ **Every Hour**
- Background check runs silently
- Card updates if status changes
- No interruption to your work

✅ **When Permits Expire**
- Automatic notifications sent to all admins
- Permit status updated to "Expired"
- Color indicator changes to red

---

## Understanding the Alerts

### Notification Levels

| Days Until Expiry | Alert Level | Action Required |
|-----------------|------------|-----------------|
| 30+ days | None | No action yet |
| 30 days | ⚠️ Warning | Plan renewal process |
| 7 days | 🔴 Urgent | Start renewal |
| 1 day | 🚨 Critical | Complete renewal TODAY |
| 0 days | ❌ Expired | Immediate action needed |

### Where You'll See Alerts

1. **Dashboard Card**
   - Red background = urgent
   - Shows total count
   - One-click refresh

2. **Notifications Dropdown**
   - Full alert message
   - Direct link to permits page
   - Mark as read when handled

3. **Quick Actions**
   - Permits button for quick access

---

## Common Tasks

### Check Current Permit Status
1. Log in to admin dashboard
2. Look at Permit Status card
3. Read the count and color

**Time Required:** 5 seconds

### View Expiring Permits
1. Click "Permits" button in Quick Actions
2. Page opens to permits list
3. They're automatically filtered or highlighted

**Time Required:** 10 seconds

### Refresh Permit Data
1. Find Permit Status card
2. Click the refresh button (↻)
3. Wait for card to update

**Time Required:** 3 seconds

### Process a Permit Renewal
1. Go to Monitoring of Requests page
2. Find the expiring permit
3. Click to view details
4. Start renewal workflow

**Time Required:** 5-10 minutes per permit

---

## Understanding the Numbers

### What the Card Shows

**Example 1: Normal Situation**
```
Permit Status
🎖️ 3 Expiring Soon
Status: Orange
```
✎ *Meaning:* 3 permits will expire within 30 days. Start planning renewals.

**Example 2: Urgent Situation**
```
Permit Status
🎖️ 7 Expiring Soon
⚠️ 2 Expired!
Status: Red
```
✎ *Meaning:* 7 permits expiring soon + 2 already expired. Urgent action needed!

---

## Receiving Notifications

### How Notifications Work

When a permit reaches certain milestones, all admins get a notification:

**30 Days Before Expiry**
- Title: ⚠️ Permits Expiring in 30 Days
- Action: Start planning renewal

**7 Days Before Expiry**
- Title: 🔴 URGENT: Permits Expiring in 7 Days
- Action: Begin renewal process

**1 Day Before Expiry**
- Title: 🚨 FINAL WARNING: Permits Expire Tomorrow!
- Action: Complete renewal TODAY

**On Expiry Date**
- Title: ❌ Permits Now Expired
- Action: Immediate action required

### Viewing Notifications
1. Click the notification bell icon (🔔) in header
2. Look for permit-related messages
3. Click to view and navigate to permits page
4. Click to mark as read

---

## Quick Reference

### Dashboard Card Location
- Top of page
- Second row, 6th position
- Orange/red color

### Quick Actions Button
- Right sidebar
- Yellow icon (🎖️)
- Text: "Permits"

### Full Permit Page
- Admin → Pages → Monitoring of Requests
- Or click any permit notification
- Or use Quick Actions → Permits

### Auto-Check Schedule
- On page load: Immediate
- Background: Every 60 minutes
- Manual: Click refresh button anytime

---

## Tips & Tricks

### ⚡ Pro Tip #1: Dashboard at a Glance
The Permit Status card uses colors to quickly show status:
- **Orange** = Normal, expiring within 30 days
- **Red** = Critical, 1 day or already expired

### ⚡ Pro Tip #2: Quick Navigate
Click "Permits" in Quick Actions for instant access to all permit-related tasks.

### ⚡ Pro Tip #3: Notification Alert
Don't ignore red permit notifications - they mean action is needed.

### ⚡ Pro Tip #4: Refresh Before Planning
Click the refresh button before planning renewals to ensure you have latest data.

### ⚡ Pro Tip #5: Check Daily
Make checking the Permit Status card part of your daily admin routine.

---

## Troubleshooting

### Card Not Showing?
- Browser cache issue
- Clear your browser cache
- Refresh the page

### Numbers Look Wrong?
- Permit dates may not be set
- Check permit details in monitoring page
- Ensure dates are in correct format

### Not Getting Notifications?
- Check your notification settings
- Ensure admin role is active
- Check notification dropdown

### Refresh Button Not Working?
- Ensure you're logged in as admin
- Check browser console for errors
- Verify API endpoint is accessible

---

## What Happens in the Background

### Automatic Processes (You Don't Need to Do Anything)

1. **Check Expiration Dates**
   - System queries database for expiry dates
   - Compares against today's date
   - Checks 30-day, 7-day, and 1-day thresholds

2. **Send Notifications**
   - Creates notification for each threshold
   - Sends to ALL admin users
   - Stores in notification system

3. **Update Status**
   - Automatically marks expired permits
   - Changes status to "Expired"
   - Updates in database

4. **Hourly Background Check**
   - Runs silently every 60 minutes
   - Updates card if changes detected
   - No notification spam

---

## FAQ

**Q: Will my system be slowed down?**
A: No. The checks use efficient queries and only run hourly.

**Q: What if I don't click the refresh button?**
A: The system automatically checks hourly, so you'll still get updates.

**Q: Can I turn off notifications?**
A: Yes, through your notification settings, but not recommended.

**Q: What if a permit expiration date is wrong?**
A: Edit the permit details to correct the date. System will re-calculate.

**Q: Can residents see this?**
A: No, this is admin-only. Residents don't have access.

**Q: How often does it check?**
A: Every 60 minutes automatically, plus on page load and when you click refresh.

**Q: Can I set custom expiry thresholds?**
A: Not currently, but can be added if needed. Current: 30, 7, and 1 day.

---

## Need Help?

For more detailed information, see:
- **Technical Details:** `PERMIT_EXPIRY_ACTIVATION.md`
- **Activation Status:** `ACTIVATION_CHECKLIST.md`
- **System Admin:** Contact your system administrator

---

**Happy managing! Your permits are now being monitored automatically. ✅**
