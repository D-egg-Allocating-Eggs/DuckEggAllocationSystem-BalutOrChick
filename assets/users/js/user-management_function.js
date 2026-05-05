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

// Close modal when clicking outside
window.onclick = function (event) {
    const userModal = document.getElementById('userModal');
    const viewModal = document.getElementById('viewUserModal');
    if (event.target === userModal) {
        closeModal();
    }
    if (event.target === viewModal) {
        closeViewModal();
    }
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

// Enhanced View Modal Function
async function openViewModal(userId) {
    const modal = document.getElementById('viewUserModal');
    const modalBody = document.getElementById('viewModalBody');

    modal.classList.add('active');
    modalBody.innerHTML = '<div class="loading-spinner-small" style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading user data...</div>';

    try {
        const response = await fetch(`../../controller/user-view.php?user_id=${userId}`);
        const result = await response.json();

        if (result.success && result.data) {
            displayUserDetails(result.data, userId);
        } else {
            modalBody.innerHTML = '<div class="error-message" style="text-align: center; padding: 2rem; color: #ef4444;"><i class="fas fa-exclamation-circle"></i> Failed to load user data</div>';
        }
    } catch (error) {
        console.error('Error:', error);
        modalBody.innerHTML = '<div class="error-message" style="text-align: center; padding: 2rem; color: #ef4444;"><i class="fas fa-exclamation-circle"></i> An error occurred</div>';
    }
}

function displayUserDetails(data, userId) {
    const user = data.user || {};
    const eggRecords = data.eggRecords || [];
    const activities = data.activities || [];
    const viewerRole = userRole;

    let html = `
        <div class="user-details-container">
            <!-- Basic User Info Section -->
            <div class="detail-section">
                <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Username:</label>
                        <span class="detail-value">${escapeHtml(user.username || 'N/A')}</span>
                    </div>
                    ${viewerRole === 'admin' ? `
                    <div class="detail-item">
                        <label>Email:</label>
                        <span class="detail-value">${escapeHtml(user.email || 'N/A')}</span>
                    </div>
                    ` : ''}
                    <div class="detail-item">
                        <label>Role:</label>
                        <span class="role-badge ${user.user_role || 'user'}">${ucfirst(user.user_role || 'User')}</span>
                    </div>
                    ${viewerRole === 'admin' ? `
                    <div class="detail-item">
                        <label>Verification Status:</label>
                        ${user.is_verified ?
                '<span class="badge badge-verified"><i class="fas fa-check-circle"></i> Verified</span>' :
                '<span class="badge badge-unverified"><i class="fas fa-clock"></i> Not Verified</span>'
            }
                    </div>
                    ` : ''}
                    <div class="detail-item">
                        <label>Joined:</label>
                        <span class="detail-value">${formatDate(user.created_at)}</span>
                    </div>
                </div>
            </div>
    `;

    // Role-based additional details
    if (viewerRole === 'admin') {
        // Admin sees activity summary + system stats
        html += `
            <div class="detail-section">
                <h4><i class="fas fa-chart-bar"></i> System Statistics</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Total Egg Batches:</label>
                        <span class="detail-value">${data.totalBatches || 0}</span>
                    </div>
                    <div class="detail-item">
                        <label>Total Balut:</label>
                        <span class="detail-value">${data.totalBalut || 0}</span>
                    </div>
                    <div class="detail-item">
                        <label>Total Chicks:</label>
                        <span class="detail-value">${data.totalChicks || 0}</span>
                    </div>
                    <div class="detail-item">
                        <label>Total Failed:</label>
                        <span class="detail-value">${data.totalFailed || 0}</span>
                    </div>
                </div>
            </div>
        `;
    } else if (viewerRole === 'manager') {
        // Manager sees limited summary
        html += `
            <div class="detail-section">
                <h4><i class="fas fa-chart-bar"></i> Summary</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Total Egg Batches:</label>
                        <span class="detail-value">${data.totalBatches || 0}</span>
                    </div>
                    <div class="detail-item">
                        <label>Recent Activity Count:</label>
                        <span class="detail-value">${activities.length}</span>
                    </div>
                </div>
            </div>
        `;
    }

    // Activity Logs Section (if available)
    if (activities.length > 0) {
        const maxLogs = viewerRole === 'admin' ? 10 : 5;
        const recentActivities = activities.slice(0, maxLogs);

        html += `
            <div class="detail-section">
                <h4><i class="fas fa-history"></i> Recent Activity Logs (Last ${recentActivities.length})</h4>
                <div class="activity-list">
                    ${recentActivities.map(activity => `
                        <div class="activity-item">
                            <div class="activity-action">
                                <i class="fas fa-bell"></i>
                                <span>${escapeHtml(activity.action)}</span>
                            </div>
                            <div class="activity-time-small">${timeAgo(activity.log_date)}</div>
                        </div>
                    `).join('')}
                </div>
                ${activities.length > maxLogs ? `<p class="more-logs">+ ${activities.length - maxLogs} more logs available</p>` : ''}
            </div>
        `;
    } else {
        html += `
            <div class="detail-section">
                <h4><i class="fas fa-history"></i> Activity Logs</h4>
                <p class="no-data"><i class="fas fa-info-circle"></i> No activity logs found</p>
            </div>
        `;
    }

    // Egg records summary (if exists)
    if (eggRecords.length > 0) {
        html += `
            <div class="detail-section">
                <h4><i class="fas fa-egg"></i> Recent Egg Batches</h4>
                <div class="table-scroll-wrapper">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>Batch #</th>
                                <th>Total Eggs</th>
                                <th>Status</th>
                                <th>Started</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${eggRecords.slice(0, 5).map(batch => `
                                <tr>
                                    <td>#${batch.batch_number || batch.egg_id}</td>
                                    <td>${batch.total_egg}</td>
                                    <td><span class="badge ${batch.status === 'incubating' ? 'badge-warning' : 'badge-success'}">${batch.status}</span></td>
                                    <td>${formatDate(batch.date_started_incubation)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    html += `</div>`;
    document.getElementById('viewModalBody').innerHTML = html;
}

function closeViewModal() {
    const modal = document.getElementById('viewUserModal');
    modal.classList.remove('active');
    document.getElementById('viewModalBody').innerHTML = '';
}

// Fix openEditModal function to hide email field properly
function openEditModal(id, username, role) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit User';
    document.getElementById('editUserId').value = id;
    document.getElementById('modalUsername').value = username;
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalPassword').required = false;
    document.getElementById('passwordLabel').innerHTML = 'Password (leave blank to keep unchanged)';
    document.getElementById('modalRole').value = role;

    // Hide email field for edit mode
    const emailFieldGroup = document.getElementById('emailFieldGroup');
    if (emailFieldGroup) {
        emailFieldGroup.style.display = 'none';
    }

    // Hide verification checkbox for edit mode
    const verificationGroup = document.getElementById('verificationCheckboxGroup');
    if (verificationGroup) {
        verificationGroup.style.display = 'none';
    }

    document.getElementById('userModal').classList.add('active');
}

// Fix closeModal function to reset form properly
function closeModal() {
    document.getElementById('userModal').classList.remove('active');

    // Reset form
    const form = document.getElementById('userForm');
    if (form) {
        form.reset();
    }

    document.getElementById('editUserId').value = '';
    document.getElementById('modalPassword').required = true;
    document.getElementById('passwordLabel').innerHTML = 'Password *';

    // Show email field for add mode
    const emailFieldGroup = document.getElementById('emailFieldGroup');
    if (emailFieldGroup) {
        emailFieldGroup.style.display = 'block';
    }

    // Show verification checkbox for add mode
    const verificationGroup = document.getElementById('verificationCheckboxGroup');
    if (verificationGroup) {
        verificationGroup.style.display = 'block';
    }
}

// Fix openAddModal function
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
    document.getElementById('editUserId').value = '';
    document.getElementById('modalUsername').value = '';

    const emailInput = document.getElementById('modalEmail');
    if (emailInput) {
        emailInput.value = '';
        emailInput.required = true;
    }

    document.getElementById('modalPassword').value = '';
    document.getElementById('modalPassword').required = true;
    document.getElementById('passwordLabel').innerHTML = 'Password *';
    document.getElementById('modalRole').value = 'user';

    const sendVerificationCheckbox = document.getElementById('modalSendVerification');
    if (sendVerificationCheckbox) {
        sendVerificationCheckbox.checked = true;
    }

    // Show email field for add mode
    const emailFieldGroup = document.getElementById('emailFieldGroup');
    if (emailFieldGroup) {
        emailFieldGroup.style.display = 'block';
    }

    // Show verification checkbox for add mode
    const verificationGroup = document.getElementById('verificationCheckboxGroup');
    if (verificationGroup) {
        verificationGroup.style.display = 'block';
    }

    document.getElementById('userModal').classList.add('active');
}

// Fix saveUser function to handle auto-verified users properly
async function saveUser(event) {
    event.preventDefault();

    const userId = document.getElementById('editUserId').value;
    const username = document.getElementById('modalUsername').value;
    const email = document.getElementById('modalEmail') ? document.getElementById('modalEmail').value : '';
    const password = document.getElementById('modalPassword').value;
    const role = document.getElementById('modalRole').value;
    const sendVerification = document.getElementById('modalSendVerification')?.checked ? 1 : 0;

    // Validation
    if (!username || username.length < 3) {
        showToast('Username must be at least 3 characters', 'error');
        return;
    }

    if (!userId && (!email || !email.includes('@'))) {
        showToast('Please enter a valid email address', 'error');
        return;
    }

    if (!userId && (!password || password.length < 6)) {
        showToast('Password must be at least 6 characters', 'error');
        return;
    }

    const saveBtn = document.getElementById('saveBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    try {
        let url, method, data;

        if (userId) {
            url = '../../controller/user-update.php';
            method = 'POST';
            data = { user_id: userId, username: username, role: role };
        } else {
            url = '../../controller/user-create.php';
            method = 'POST';
            data = {
                username: username,
                email: email,
                password: password,
                role: role,
                send_verification: sendVerification
            };
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
            closeModal();

            // Handle different response types
            if (result.auto_verified) {
                // User is auto-verified - no verification needed
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else if (result.verification_link) {
                // Show verification link dialog
                showVerificationLinkDialog(result.message, result.verification_link, result.email_sent);
            } else {
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

// Updated showVerificationLinkDialog function
function showVerificationLinkDialog(message, verificationLink, emailSent = false) {
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

    const emailStatusIcon = emailSent ? 'fa-check-circle' : 'fa-exclamation-triangle';
    const emailStatusColor = emailSent ? '#10b981' : '#f59e0b';
    const emailStatusText = emailSent ? 'Email sent successfully' : 'Email sending failed (check your mail configuration)';

    modal.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #10b981;">
                <i class="fas fa-check-circle"></i> User Created Successfully
            </h3>
            <button onclick="this.closest('#verificationLinkModal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <div style="margin-bottom: 20px;">
            <p style="color: #666; margin-bottom: 12px;">${escapeHtml(message)}</p>
            
            <div style="background: ${emailSent ? '#f0fdf4' : '#fffbeb'}; border: 1px solid ${emailSent ? '#bbf7d0' : '#fde68a'}; border-radius: 8px; padding: 16px; margin-top: 16px;">
                <p style="margin: 0 0 8px 0; font-weight: 600; color: ${emailSent ? '#166534' : '#92400e'};">
                    <i class="fas ${emailStatusIcon}"></i> ${emailStatusText}
                </p>
                
                <div style="margin-top: 12px;">
                    <p style="margin: 0 0 8px 0; font-weight: 600; color: #1e293b;">
                        <i class="fas fa-link"></i> Verification Link (Click to copy):
                    </p>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="verificationLinkInput" value="${escapeHtml(verificationLink)}" 
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
            </div>
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
    window.currentVerificationLink = verificationLink;
}

function copyVerificationLink() {
    const input = document.getElementById('verificationLinkInput');
    if (input) {
        input.select();
        document.execCommand('copy');

        const copyBtn = event.target.closest('button');
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => {
            copyBtn.innerHTML = originalText;
        }, 2000);

        showToast('Link copied to clipboard!', 'success');
    }
}

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

// Helper functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function timeAgo(dateString) {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
    const weeks = Math.floor(days / 7);
    if (weeks < 4) return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months} month${months > 1 ? 's' : ''} ago`;
    const years = Math.floor(days / 365);
    return `${years} year${years > 1 ? 's' : ''} ago`;
}

// Export functions
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
        const username = document.querySelector('.table-container h3')?.innerText.replace('User Records: ', '') || 'User';
        const clone = element.cloneNode(true);
        clone.style.width = '800px';
        clone.style.padding = '20px';
        clone.style.backgroundColor = 'white';
        const titleDiv = document.createElement('div');
        titleDiv.innerHTML = `<div style="text-align:center;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #10b981;"><h1 style="color:#1e293b;">EggFlow - Egg Records</h1><p>User: ${escapeHtml(username)} | Generated: ${new Date().toLocaleString()}</p></div>`;
        clone.insertBefore(titleDiv, clone.firstChild);
        document.body.appendChild(clone);
        const canvas = await html2canvas(clone, {
            scale: 2,
            backgroundColor: '#ffffff'
        });
        document.body.removeChild(clone);
        const { jsPDF } = window.jspdf;
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
        const username = document.querySelector('.table-container h3')?.innerText.replace('User Records: ', '') || 'User';
        const clone = element.cloneNode(true);
        clone.style.width = '800px';
        clone.style.padding = '20px';
        clone.style.backgroundColor = 'white';
        const titleDiv = document.createElement('div');
        titleDiv.innerHTML = `<div style="text-align:center;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #10b981;"><h1 style="color:#1e293b;">EggFlow - Activity Logs</h1><p>User: ${escapeHtml(username)} | Generated: ${new Date().toLocaleString()}</p></div>`;
        clone.insertBefore(titleDiv, clone.firstChild);
        document.body.appendChild(clone);
        const canvas = await html2canvas(clone, {
            scale: 2,
            backgroundColor: '#ffffff'
        });
        document.body.removeChild(clone);
        const { jsPDF } = window.jspdf;
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