// Add animation order to event cards
document.addEventListener('DOMContentLoaded', function() {
    const eventCards = document.querySelectorAll('.event-card');
    eventCards.forEach((card, index) => {
        card.style.setProperty('--animation-order', index);
    });

    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
});

// --- new/modified functions for consistent modal handling ---
const ADMIN_BASE = '/capstone/employee/admin';

function fetchJson(url) {
	// fetch with content-type safety and helpful errors
	return fetch(url, { credentials: 'same-origin' }).then(res => {
		if (!res.ok) throw new Error('Network response was not ok (' + res.status + ')');
		const ctype = res.headers.get('content-type') || '';
		if (!ctype.includes('application/json')) {
			return res.text().then(text => { throw new Error('Expected JSON but received: ' + (text ? text.slice(0,200) : '[empty response]')); });
		}
		return res.json();
	});
}

function escapeHtml(s) {
	if (typeof s !== 'string') return '';
	return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function openVolunteersModal(eventId) {
	const volunteersUrl = `${ADMIN_BASE}/get_event_volunteers.php?event_id=${encodeURIComponent(eventId)}`;
	const modalEl = document.getElementById('volunteersModal');
	const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
	const volunteersList = document.getElementById('volunteersList');
	const volunteerCount = document.getElementById('volunteerCountText');

	if (volunteersList) volunteersList.innerHTML = '<p>Loading...</p>';
	if (volunteerCount) volunteerCount.textContent = 'Loading...';

	fetchJson(volunteersUrl)
		.then(data => {
			if (modalTitle) modalTitle.textContent = data.event_title || 'Event Volunteers';
			if (volunteersList) {
				const volunteers = data.volunteers || [];

				// Separate by status
				const approved = volunteers.filter(v => v.status === 'approved');
				const pending = volunteers.filter(v => v.status === 'pending');
				const rejected = volunteers.filter(v => v.status === 'rejected');

				let html = '';

				// Approved section
				html += `<div class="vol-section"><h4>Approved Volunteers (${approved.length})</h4>`;
				if (approved.length === 0) {
					html += '<div class="empty-state"><p>No approved volunteers yet.</p></div>';
				} else {
					approved.forEach(v => {
						const profileLink = `${ADMIN_BASE}/community_service.php?view=history&resident_id=${encodeURIComponent(v.resident_id)}`;
						const attendanceLabel = v.attendance_status ? (v.attendance_status === 'attended' ? 'Attended' : 'Not Attended') : 'Pending';
						html += `
							<div class="volunteer-item" style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid #f1f1f1;">
								<div>
									<strong><a href="${profileLink}" target="_blank" rel="noopener noreferrer">${escapeHtml(v.name)}</a></strong>
									<div class="volunteer-contact" style="color:#666;font-size:0.9rem;">${escapeHtml(v.contact || '')}</div>
								</div>
								<div>
									<span class="status-badge status-approved">${escapeHtml(attendanceLabel)}</span>
								</div>
							</div>
						`;
					});
				}
				html += `</div>`;

				// Pending section — name is plain text and shows Approve / Reject buttons
				html += `<div class="vol-section" style="margin-top:0.75rem;"><h4>Pending Requests (${pending.length})</h4>`;
				if (pending.length === 0) {
					html += '<div class="empty-state"><p>No pending requests.</p></div>';
				} else {
					pending.forEach(v => {
						// escape name for inclusion in single-quoted JS call
						const jsName = (v.name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
						html += `
							<div class="volunteer-item" style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid #f1f1f1;">
								<div>
									<strong>${escapeHtml(v.name)}</strong>
									<div class="volunteer-contact" style="color:#666;font-size:0.9rem;">${escapeHtml(v.contact || '')}</div>
								</div>
								<div style="display:flex;gap:0.5rem;">
									<button class="btn btn-sm btn-success" type="button" onclick="approveVolunteer(${v.id})"><i class="fas fa-check"></i> Approve</button>
									<button class="btn btn-sm btn-danger" type="button" onclick="openRejectModal(${v.id}, '${jsName}')"><i class="fas fa-times"></i> Reject</button>
								</div>
							</div>
						`;
					});
				}
				html += `</div>`;

				// Optional rejected section
				if (rejected.length > 0) {
					html += `<div class="vol-section" style="margin-top:0.75rem;"><h4>Rejected (${rejected.length})</h4>`;
					rejected.forEach(v => {
						html += `
							<div class="volunteer-item" style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid #f1f1f1;">
								<div>
									<strong>${escapeHtml(v.name)}</strong>
									<div class="volunteer-contact" style="color:#666;font-size:0.9rem;">${escapeHtml(v.contact || '')}</div>
								</div>
								<div>
									<span class="status-badge status-rejected">Rejected</span>
								</div>
							</div>
						`;
					});
					html += `</div>`;
				}

				volunteersList.innerHTML = html;
				volunteersList.closest('#volunteersModal').dataset.eventId = eventId;
			}
			if (volunteerCount) volunteerCount.textContent = `${(data.volunteers || []).length} Volunteers`;
			// show modal
			if (window.bootstrap && modalEl) {
				new bootstrap.Modal(modalEl).show();
			} else if (modalEl) {
				modalEl.classList.add('active');
				modalEl.style.display = 'flex';
				document.body.style.overflow = 'hidden';
			}
		})
		.catch(err => {
			console.error('Failed to load volunteers:', err);
			if (volunteersList) volunteersList.innerHTML = '<div class="empty-state"><p>Error loading volunteers. (' + (err.message || '') + ')</p></div>';
			if (volunteerCount) volunteerCount.textContent = '0 Volunteers';
			if (modalEl) {
				if (window.bootstrap) new bootstrap.Modal(modalEl).show();
				else { modalEl.classList.add('active'); modalEl.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
			}
		});
}

function openApplicationsModal(eventId) {
	const appsUrl = `${ADMIN_BASE}/get_volunteer_applications.php?event_id=${encodeURIComponent(eventId)}`;
	const modalEl = document.getElementById('applicationsModal');
	const list = document.getElementById('applicationsList');
	if (list) list.innerHTML = '<p>Loading...</p>';

	fetchJson(appsUrl)
		.then(data => {
			const apps = data.applications || [];
			if (list) {
				if (apps.length === 0) {
					list.innerHTML = '<div class="empty-state"><p>No pending applications.</p></div>';
				} else {
					list.innerHTML = apps.map(app => `
						<div class="volunteer-item" style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid #f1f1f1;">
							<div>
								<strong>${escapeHtml(app.name)}</strong>
								<div class="volunteer-contact" style="color:#666;font-size:0.9rem;">${escapeHtml(app.contact || '')}</div>
							</div>
							<div class="application-actions">
								<button class="btn btn-sm btn-success" type="button" onclick="approveVolunteer(${app.id})"><i class="fas fa-check"></i> Approve</button>
								<button class="btn btn-sm btn-danger" type="button" onclick="openRejectModal(${app.id}, '${escapeHtml(app.name).replace(/'/g, "\\'")}')"><i class="fas fa-times"></i> Reject</button>
							</div>
						</div>
					`).join('');
				}
			}
			if (modalEl) {
				if (window.bootstrap) new bootstrap.Modal(modalEl).show();
				else { modalEl.classList.add('active'); modalEl.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
			}
		})
		.catch(err => {
			console.error('Failed to load applications:', err);
			if (list) list.innerHTML = '<div class="empty-state"><p>Error loading applications. (' + (err.message || '') + ')</p></div>';
			if (modalEl) {
				if (window.bootstrap) new bootstrap.Modal(modalEl).show();
				else { modalEl.classList.add('active'); modalEl.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
			}
		});
}

// Backwards-compatible wrappers used by inline onclick attributes
function showEventVolunteers(eventId) {
	openVolunteersModal(eventId);
}

function showVolunteerApplications(eventId) {
	if (eventId) return openApplicationsModal(eventId);
	// fallback to dataset on volunteersModal
	const vm = document.getElementById('volunteersModal');
	const eid = vm ? vm.dataset.eventId : null;
	if (eid) return openApplicationsModal(eid);
	alert('Event not selected.');
}

// keep the older showVolunteers name to support other pages that call it
function showVolunteers(eventId) {
	openVolunteersModal(eventId);
}

// Enhanced animations for modals
function animateModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('modal-animated');
    setTimeout(() => modal.classList.remove('modal-animated'), 300);
}

// Smooth scrolling to sections
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    section.scrollIntoView({ behavior: 'smooth' });
}
