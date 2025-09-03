<div class="modal fade" id="volunteersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Event Volunteers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="volunteers-list" id="volunteersList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.volunteers-list {
    max-height: 400px;
    overflow-y: auto;
}

.volunteer-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #eee;
}

.volunteer-item:last-child {
    border-bottom: none;
}

.volunteer-info h4 {
    margin: 0;
    color: #333;
    font-size: 1.1rem;
}

.volunteer-info p {
    margin: 0.25rem 0 0;
    color: #666;
    font-size: 0.9rem;
}

.modal-animated {
    animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>
