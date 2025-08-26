/**
 * CareerNest Employer Dashboard JavaScript
 */

document.addEventListener("DOMContentLoaded", function () {
  // Profile edit toggle functionality
  const toggleBtn = document.getElementById("cn-toggle-edit");
  const editForm = document.getElementById("cn-profile-edit-form");
  const editText = document.querySelector(".cn-edit-text");
  const cancelText = document.querySelector(".cn-cancel-text");
  const cancelBtn = document.getElementById("cn-cancel-edit");

  // Get all profile display sections (exclude the edit form and its children)
  const profileSections = document.querySelectorAll(
    ".cn-dashboard-main > .cn-dashboard-section:not(.cn-profile-edit-form)"
  );

  if (toggleBtn && editForm) {
    toggleBtn.addEventListener("click", function () {
      const isEditing = editForm.style.display !== "none";

      if (isEditing) {
        // Switch to view mode
        exitEditMode();
      } else {
        // Switch to edit mode
        enterEditMode();
      }
    });
  }

  // Cancel button functionality
  if (cancelBtn) {
    cancelBtn.addEventListener("click", function () {
      exitEditMode();
    });
  }

  function enterEditMode() {
    // Hide all profile display sections
    profileSections.forEach(function (section) {
      section.style.display = "none";
    });

    // Show edit form
    editForm.style.display = "block";

    // Show all form sections within the edit form
    const formSections = editForm.querySelectorAll(".cn-dashboard-section");
    formSections.forEach(function (section) {
      section.style.display = "block";
    });

    // Update button text
    editText.style.display = "none";
    cancelText.style.display = "inline";
  }

  function exitEditMode() {
    // Show all profile display sections
    profileSections.forEach(function (section) {
      section.style.display = "block";
    });

    // Hide edit form
    editForm.style.display = "none";

    // Update button text
    editText.style.display = "inline";
    cancelText.style.display = "none";
  }

  // Auto-hide success message after 5 seconds
  const successMessage = document.querySelector(".cn-profile-success");
  if (successMessage) {
    setTimeout(function () {
      successMessage.style.opacity = "0";
      setTimeout(function () {
        successMessage.style.display = "none";
      }, 300);
    }, 5000);
  }

  // Form validation enhancement
  const profileForm = document.querySelector(".cn-profile-form");
  if (profileForm) {
    profileForm.addEventListener("submit", function (e) {
      const companyName = document.getElementById("company_name");
      if (companyName && companyName.value.trim() === "") {
        e.preventDefault();
        alert("Company name is required.");
        companyName.focus();
        return false;
      }

      // Validate email if provided
      const contactEmail = document.getElementById("contact_email");
      if (contactEmail && contactEmail.value.trim() !== "") {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(contactEmail.value.trim())) {
          e.preventDefault();
          alert("Please enter a valid email address.");
          contactEmail.focus();
          return false;
        }
      }

      // Validate website URL if provided
      const website = document.getElementById("website");
      if (website && website.value.trim() !== "") {
        try {
          new URL(website.value.trim());
        } catch (e) {
          e.preventDefault();
          alert("Please enter a valid website URL.");
          website.focus();
          return false;
        }
      }
    });
  }

  // Frontend action handlers
  initializeFrontendActions();
});

/**
 * Initialize frontend action handlers for job and application management
 */
function initializeFrontendActions() {
  // Post New Job buttons
  const showJobFormButtons = document.querySelectorAll("#cn-show-job-form");
  showJobFormButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      showNoticeModal(
        "Job Posting",
        "Job posting functionality will be available in the next update. For now, please contact your administrator to post new jobs.",
        "info"
      );
    });
  });

  // Manage Jobs button
  const showJobManagementBtn = document.getElementById(
    "cn-show-job-management"
  );
  if (showJobManagementBtn) {
    showJobManagementBtn.addEventListener("click", function () {
      showNoticeModal(
        "Job Management",
        "Advanced job management features will be available in the next update. You can currently view and edit individual jobs using the buttons on each job card.",
        "info"
      );
    });
  }

  // View All Applications buttons
  const showAllApplicationsButtons = document.querySelectorAll(
    "#cn-show-all-applications"
  );
  showAllApplicationsButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      showNoticeModal(
        "Application Management",
        "Comprehensive application management will be available in the next update. You can currently review individual applications using the 'Review Application' buttons.",
        "info"
      );
    });
  });

  // Edit Job buttons
  const editJobButtons = document.querySelectorAll(".cn-edit-job");
  editJobButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      const jobId = this.getAttribute("data-job-id");
      showNoticeModal(
        "Edit Job",
        "Frontend job editing will be available in the next update. For now, please contact your administrator to edit this job.",
        "info"
      );
    });
  });

  // View Applications buttons
  const viewApplicationsButtons = document.querySelectorAll(
    ".cn-view-applications"
  );
  viewApplicationsButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      const jobId = this.getAttribute("data-job-id");
      showNoticeModal(
        "View Applications",
        "Application viewing functionality will be available in the next update. You can see recent applications in the dashboard below.",
        "info"
      );
    });
  });

  // Review Application buttons
  const reviewApplicationButtons = document.querySelectorAll(
    ".cn-review-application"
  );
  reviewApplicationButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      const appId = this.getAttribute("data-app-id");
      showNoticeModal(
        "Review Application",
        "Application review functionality will be available in the next update. For now, you can download resumes using the resume links.",
        "info"
      );
    });
  });
}

/**
 * Show a modal notice to the user
 */
function showNoticeModal(title, message, type = "info") {
  // Create modal if it doesn't exist
  let modal = document.getElementById("cn-notice-modal");
  if (!modal) {
    modal = document.createElement("div");
    modal.id = "cn-notice-modal";
    modal.className = "cn-modal";
    modal.innerHTML = `
      <div class="cn-modal-content">
        <div class="cn-modal-header">
          <h3 id="cn-modal-title"></h3>
          <button type="button" class="cn-modal-close">&times;</button>
        </div>
        <div class="cn-modal-body">
          <p id="cn-modal-message"></p>
        </div>
        <div class="cn-modal-footer">
          <button type="button" class="cn-btn cn-btn-primary cn-modal-ok">OK</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    // Add modal styles
    if (!document.getElementById("cn-modal-styles")) {
      const styles = document.createElement("style");
      styles.id = "cn-modal-styles";
      styles.textContent = `
        .cn-modal {
          display: none;
          position: fixed;
          z-index: 10000;
          left: 0;
          top: 0;
          width: 100%;
          height: 100%;
          background-color: rgba(0, 0, 0, 0.5);
          animation: fadeIn 0.3s ease;
        }
        
        .cn-modal-content {
          background-color: white;
          margin: 10% auto;
          padding: 0;
          border-radius: 8px;
          width: 90%;
          max-width: 500px;
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
          animation: slideIn 0.3s ease;
        }
        
        .cn-modal-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 1.5rem;
          border-bottom: 1px solid #e0e0e0;
        }
        
        .cn-modal-header h3 {
          margin: 0;
          color: #333;
          font-size: 1.2rem;
        }
        
        .cn-modal-close {
          background: none;
          border: none;
          font-size: 1.5rem;
          cursor: pointer;
          color: #666;
          padding: 0;
          width: 30px;
          height: 30px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        
        .cn-modal-close:hover {
          color: #333;
        }
        
        .cn-modal-body {
          padding: 1.5rem;
        }
        
        .cn-modal-body p {
          margin: 0;
          color: #555;
          line-height: 1.5;
        }
        
        .cn-modal-footer {
          padding: 1rem 1.5rem;
          border-top: 1px solid #e0e0e0;
          text-align: right;
        }
        
        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        
        @keyframes slideIn {
          from { transform: translateY(-50px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
      `;
      document.head.appendChild(styles);
    }

    // Add event listeners
    const closeBtn = modal.querySelector(".cn-modal-close");
    const okBtn = modal.querySelector(".cn-modal-ok");

    function closeModal() {
      modal.style.display = "none";
    }

    closeBtn.addEventListener("click", closeModal);
    okBtn.addEventListener("click", closeModal);

    // Close on outside click
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        closeModal();
      }
    });
  }

  // Update modal content
  document.getElementById("cn-modal-title").textContent = title;
  document.getElementById("cn-modal-message").textContent = message;

  // Show modal
  modal.style.display = "block";
}
