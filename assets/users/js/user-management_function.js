const userRole = document.body.dataset.userRole;
const currentUserId = parseInt(document.body.dataset.currentUserId);

// Mobile menu functions
function toggleMobileMenu() {
    document.getElementById('sidebar').classList.toggle('open');
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.style.display = document.getElementById('sidebar').classList.contains('open') ? 'block' : 'none';
    }
}

function closeMobileMenu() {
    document.getElementById('sidebar').classList.remove('open');
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) overlay.style.display = 'none';
}

if (document.getElementById('mobileMenuBtn')) {
    document.getElementById('mobileMenuBtn').addEventListener('click', toggleMobileMenu);
}

// Search/Filter function
function filterUsers() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#userTableBody tr');
    rows.forEach(row => {
        const username = row.getAttribute('data-username') || '';
        row.style.display = username.includes(searchTerm) ? '' : 'none';
    });
}


function openEditModal(id, username, role) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit User';
    document.getElementById('editUserId').value = id;
    document.getElementById('modalUsername').value = username;
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalPassword').required = false;
    document.getElementById('passwordLabel').textContent = 'Password (leave blank to keep unchanged)';
    document.getElementById('modalRole').value = role;
    document.getElementById('userModal').classList.add('active');
}

function closeModal() {
    document.getElementById('userModal').classList.remove('active');
}

// Update the openAddModal function
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
    document.getElementById('editUserId').value = '';
    document.getElementById('modalUsername').value = '';
    document.getElementById('modalEmail').value = '';
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalPassword').required = true;
    document.getElementById('passwordLabel').innerHTML = 'Password *';
    document.getElementById('modalRole').value = 'user';
    document.getElementById('modalSendVerification').checked = true;
    document.getElementById('userModal').style.display = 'block';
}

async function saveUser(event) {
    event.preventDefault();

    const userId = document.getElementById('editUserId').value;
    const username = document.getElementById('modalUsername').value;
    const email = document.getElementById('modalEmail').value;
    const password = document.getElementById('modalPassword').value;
    const role = document.getElementById('modalRole').value;
    const sendVerification = document.getElementById('modalSendVerification')?.checked ? 1 : 0;

    const saveBtn = document.getElementById('saveBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    try {
        let url, method, data;

        if (userId) {
            url = '../../controller/user-update.php';
            method = 'POST';
            data = { user_id: userId, username: username, role: role, email: email };
        } else {
            url = '../../controller/user-create.php';
            method = 'POST';
            data = { username: username, email: email, password: password, role: role, send_verification: sendVerification };
        }

        const formData = new FormData();
        for (let key in data) {
            formData.append(key, data[key]);
        }

        const response = await fetch(url, {
            method: method,
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Close modal first
            closeModal();
            
            // Show result in a copyable dialog
            if (result.verification_link && !result.email_sent) {
                // Email failed - show link prominently
                showVerificationLinkDialog(result.message, result.verification_link);
            } else if (result.verification_link && result.email_sent) {
                // Email sent - still show link for testing
                showVerificationLinkDialog(result.message + " (Check spam folder)", result.verification_link);
            } else {
                // No verification needed (auto-verified)
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 2000);
            }
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'error');
    } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

// New function to show copyable verification link
function showVerificationLinkDialog(message, verificationLink) {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.id = 'verificationLinkModal';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    // Create modal content
    const modal = document.createElement('div');
    modal.style.cssText = `
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;
    
    modal.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #10b981;">
                <i class="fas fa-check-circle"></i> ${message.includes('failed') ? 'User Created (Email Failed)' : 'User Created Successfully'}
            </h3>
            <button onclick="this.closest('#verificationLinkModal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <div style="margin-bottom: 20px;">
            <p style="color: #666; margin-bottom: 12px;">${message}</p>
            
            ${!message.includes('auto-verified') ? `
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-top: 16px;">
                    <p style="margin: 0 0 8px 0; font-weight: 600; color: #166534;">
                        <i class="fas fa-link"></i> Verification Link (Click to copy):
                    </p>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="verificationLinkInput" value="${verificationLink}" 
                               style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 12px; background: #f9fafb;"
                               readonly>
                        <button onclick="copyVerificationLink()" style="background: #10b981; color: white; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <p style="margin: 12px 0 0 0; font-size: 12px; color: #666;">
                        <i class="fas fa-info-circle"></i> Click the link or copy it to your browser to verify the email address.
                    </p>
                </div>
            ` : `
                <div style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 8px; padding: 16px; margin-top: 16px;">
                    <p style="margin: 0; color: #0369a1;">
                        <i class="fas fa-info-circle"></i> User is auto-verified. No email verification needed.
                    </p>
                </div>
            `}
        </div>
        
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <button onclick="this.closest('#verificationLinkModal').remove(); location.reload();" 
                    style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">
                <i class="fas fa-check"></i> OK, Reload Page
            </button>
        </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Store the link for the copy function
    window.currentVerificationLink = verificationLink;
}

// Global function to copy link
function copyVerificationLink() {
    const input = document.getElementById('verificationLinkInput');
    if (input) {
        input.select();
        document.execCommand('copy');
        
        // Show temporary feedback
        const copyBtn = event.target.closest('button');
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => {
            copyBtn.innerHTML = originalText;
        }, 2000);
        
        showToast('Link copied to clipboard!', 'success');
    }
}

// Add email column to the user table display
// Update the table header and rows in PHP or dynamically

// Delete user - using controller endpoint
async function deleteUser(id, username) {
    if (!confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) return;
    const formData = new FormData();
    formData.append('user_id', id);
    try {
        const response = await fetch('../../controller/user-delete.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toastMsg');
    toastMsg.textContent = message;
    toast.className = `toast show ${type}`;
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Export functions - using export controller
function exportEggRecordsCSV(userId) {
    window.location.href = `../../controller/user-export.php?export=egg_records&user_id=${userId}`;
    showToast('Exporting egg records...', 'info');
}

function exportActivityLogsCSV(userId) {
    window.location.href = `../../controller/user-export.php?export=activity_logs&user_id=${userId}`;
    showToast('Exporting activity logs...', 'info');
}

async function exportEggRecordsPDF(userId) {
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.add('active');
    try {
        const element = document.getElementById('eggRecordsSection');
        const username = document.querySelector('.table-container h3').innerText.replace('User Records: ', '');
        const clone = element.cloneNode(true);
        clone.style.width = '800px';
        clone.style.padding = '20px';
        clone.style.backgroundColor = 'white';
        const titleDiv = document.createElement('div');
        titleDiv.innerHTML = `<div style="text-align:center;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #10b981;"><h1 style="color:#1e293b;">EggFlow - Egg Records</h1><p>User: ${username} | Generated: ${new Date().toLocaleString()}</p></div>`;
        clone.insertBefore(titleDiv, clone.firstChild);
        document.body.appendChild(clone);
        const canvas = await html2canvas(clone, {
            scale: 2,
            backgroundColor: '#ffffff'
        });
        document.body.removeChild(clone);
        const {
            jsPDF
        } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 210;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, imgWidth, imgHeight);
        pdf.save(`egg_records_${username}_${new Date().toISOString().split('T')[0]}.pdf`);
        showToast('PDF exported successfully!', 'success');
    } catch (error) {
        showToast('Error generating PDF.', 'error');
    } finally {
        loadingOverlay.classList.remove('active');
    }
}

async function exportActivityLogsPDF(userId) {
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.add('active');
    try {
        const element = document.getElementById('activityLogsSection');
        const username = document.querySelector('.table-container h3').innerText.replace('User Records: ', '');
        const clone = element.cloneNode(true);
        clone.style.width = '800px';
        clone.style.padding = '20px';
        clone.style.backgroundColor = 'white';
        const titleDiv = document.createElement('div');
        titleDiv.innerHTML = `<div style="text-align:center;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #10b981;"><h1 style="color:#1e293b;">EggFlow - Activity Logs</h1><p>User: ${username} | Generated: ${new Date().toLocaleString()}</p></div>`;
        clone.insertBefore(titleDiv, clone.firstChild);
        document.body.appendChild(clone);
        const canvas = await html2canvas(clone, {
            scale: 2,
            backgroundColor: '#ffffff'
        });
        document.body.removeChild(clone);
        const {
            jsPDF
        } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 210;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, imgWidth, imgHeight);
        pdf.save(`activity_logs_${username}_${new Date().toISOString().split('T')[0]}.pdf`);
        showToast('PDF exported successfully!', 'success');
    } catch (error) {
        showToast('Error generating PDF.', 'error');
    } finally {
        loadingOverlay.classList.remove('active');
    }
}