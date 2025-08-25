/**
 * CareerNest Applicant Dashboard JavaScript
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
      const fullName = document.getElementById("full_name");
      if (fullName && fullName.value.trim() === "") {
        e.preventDefault();
        alert("Full name is required.");
        fullName.focus();
        return false;
      }
    });
  }

  // Skills input enhancement
  const skillsInput = document.getElementById("skills_input");
  if (skillsInput) {
    skillsInput.addEventListener("blur", function () {
      // Clean up skills input (remove extra commas, spaces)
      let skills = this.value
        .split(",")
        .map((skill) => skill.trim())
        .filter((skill) => skill !== "");
      this.value = skills.join(", ");
    });
  }

  // Repeater field functionality
  initializeRepeaterFields();

  // Current job checkbox functionality
  initializeCurrentJobCheckboxes();
});

/**
 * Initialize repeater field functionality (add/remove items)
 */
function initializeRepeaterFields() {
  // Education repeater
  const addEducationBtn = document.getElementById("cn-add-education");
  const educationContainer = document.getElementById("cn-education-fields");

  if (addEducationBtn && educationContainer) {
    addEducationBtn.addEventListener("click", function () {
      addRepeaterItem(educationContainer, "education", getEducationTemplate);
    });

    // Initialize remove buttons for existing items
    initializeRemoveButtons(educationContainer);
  }

  // Experience repeater
  const addExperienceBtn = document.getElementById("cn-add-experience");
  const experienceContainer = document.getElementById("cn-experience-fields");

  if (addExperienceBtn && experienceContainer) {
    addExperienceBtn.addEventListener("click", function () {
      addRepeaterItem(experienceContainer, "experience", getExperienceTemplate);
    });

    // Initialize remove buttons for existing items
    initializeRemoveButtons(experienceContainer);
  }

  // Licenses repeater
  const addLicenseBtn = document.getElementById("cn-add-license");
  const licensesContainer = document.getElementById("cn-licenses-fields");

  if (addLicenseBtn && licensesContainer) {
    addLicenseBtn.addEventListener("click", function () {
      addRepeaterItem(licensesContainer, "licenses", getLicenseTemplate);
    });

    // Initialize remove buttons for existing items
    initializeRemoveButtons(licensesContainer);
  }

  // Links repeater
  const addLinkBtn = document.getElementById("cn-add-link");
  const linksContainer = document.getElementById("cn-links-fields");

  if (addLinkBtn && linksContainer) {
    addLinkBtn.addEventListener("click", function () {
      addRepeaterItem(linksContainer, "links", getLinkTemplate);
    });

    // Initialize remove buttons for existing items
    initializeRemoveButtons(linksContainer);
  }
}

/**
 * Add a new repeater item
 */
function addRepeaterItem(container, fieldName, templateFunction) {
  const items = container.querySelectorAll(".cn-repeater-item");
  const newIndex = items.length;
  const template = templateFunction(fieldName, newIndex);

  const div = document.createElement("div");
  div.innerHTML = template;
  const newItem = div.firstElementChild;

  container.appendChild(newItem);

  // Initialize remove button for the new item
  initializeRemoveButtons(container);

  // Initialize current job checkbox if this is experience
  if (fieldName === "experience") {
    initializeCurrentJobCheckboxes();
  }
}

/**
 * Initialize remove buttons for repeater items
 */
function initializeRemoveButtons(container) {
  const removeButtons = container.querySelectorAll(".cn-remove-item");

  removeButtons.forEach(function (button) {
    // Remove existing event listeners to prevent duplicates
    button.replaceWith(button.cloneNode(true));
  });

  // Re-select buttons after cloning
  const newRemoveButtons = container.querySelectorAll(".cn-remove-item");

  newRemoveButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      const item = this.closest(".cn-repeater-item");
      if (item) {
        item.remove();
        // Reindex remaining items
        reindexRepeaterItems(container);
      }
    });
  });
}

/**
 * Reindex repeater items after removal
 */
function reindexRepeaterItems(container) {
  const items = container.querySelectorAll(".cn-repeater-item");

  items.forEach(function (item, index) {
    item.setAttribute("data-index", index);

    // Update all input names within this item
    const inputs = item.querySelectorAll("input, textarea, select");
    inputs.forEach(function (input) {
      const name = input.getAttribute("name");
      if (name) {
        // Replace the index in the name attribute
        const newName = name.replace(/\[\d+\]/, `[${index}]`);
        input.setAttribute("name", newName);
      }
    });
  });
}

/**
 * Initialize current job checkbox functionality
 */
function initializeCurrentJobCheckboxes() {
  const currentJobCheckboxes = document.querySelectorAll(".cn-current-job");

  currentJobCheckboxes.forEach(function (checkbox) {
    checkbox.addEventListener("change", function () {
      const repeaterItem = this.closest(".cn-repeater-item");
      if (repeaterItem) {
        const endDateInput = repeaterItem.querySelector(".cn-end-date");
        if (endDateInput) {
          if (this.checked) {
            endDateInput.value = "";
            endDateInput.disabled = true;
            endDateInput.style.opacity = "0.5";
          } else {
            endDateInput.disabled = false;
            endDateInput.style.opacity = "1";
          }
        }
      }
    });

    // Initialize state on page load
    if (checkbox.checked) {
      const repeaterItem = checkbox.closest(".cn-repeater-item");
      if (repeaterItem) {
        const endDateInput = repeaterItem.querySelector(".cn-end-date");
        if (endDateInput) {
          endDateInput.disabled = true;
          endDateInput.style.opacity = "0.5";
        }
      }
    }
  });
}

/**
 * Get education item template
 */
function getEducationTemplate(fieldName, index) {
  return `
    <div class="cn-repeater-item" data-index="${index}">
      <div class="cn-form-field">
        <label>Institution</label>
        <input type="text" name="${fieldName}[${index}][institution]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Degree/Certification</label>
        <input type="text" name="${fieldName}[${index}][certification]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Completion Date</label>
        <input type="month" name="${fieldName}[${index}][end_date]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label class="cn-checkbox-label-small">
          <input type="checkbox" name="${fieldName}[${index}][complete]" value="1" class="cn-checkbox">
          <span>Completed</span>
        </label>
      </div>
      <button type="button" class="cn-btn cn-btn-small cn-btn-outline cn-remove-item">Remove</button>
    </div>
  `;
}

/**
 * Get experience item template
 */
function getExperienceTemplate(fieldName, index) {
  return `
    <div class="cn-repeater-item" data-index="${index}">
      <div class="cn-form-field">
        <label>Company</label>
        <input type="text" name="${fieldName}[${index}][company]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Job Title</label>
        <input type="text" name="${fieldName}[${index}][title]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Start Date</label>
        <input type="month" name="${fieldName}[${index}][start_date]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>End Date</label>
        <input type="month" name="${fieldName}[${index}][end_date]" class="cn-input cn-input-small cn-end-date">
      </div>
      <div class="cn-form-field">
        <label class="cn-checkbox-label-small">
          <input type="checkbox" name="${fieldName}[${index}][current]" value="1" class="cn-checkbox cn-current-job">
          <span>Current Position</span>
        </label>
      </div>
      <div class="cn-form-field">
        <label>Description</label>
        <textarea name="${fieldName}[${index}][description]" rows="3" class="cn-input cn-input-small"></textarea>
      </div>
      <button type="button" class="cn-btn cn-btn-small cn-btn-outline cn-remove-item">Remove</button>
    </div>
  `;
}

/**
 * Get license item template
 */
function getLicenseTemplate(fieldName, index) {
  return `
    <div class="cn-repeater-item" data-index="${index}">
      <div class="cn-form-field">
        <label>Name</label>
        <input type="text" name="${fieldName}[${index}][name]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Issuing Organization</label>
        <input type="text" name="${fieldName}[${index}][issuer]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Issue Date</label>
        <input type="month" name="${fieldName}[${index}][issue_date]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Expiry Date</label>
        <input type="month" name="${fieldName}[${index}][expiry_date]" class="cn-input cn-input-small">
      </div>
      <div class="cn-form-field">
        <label>Credential ID</label>
        <input type="text" name="${fieldName}[${index}][credential_id]" class="cn-input cn-input-small">
      </div>
      <button type="button" class="cn-btn cn-btn-small cn-btn-outline cn-remove-item">Remove</button>
    </div>
  `;
}

/**
 * Get link item template
 */
function getLinkTemplate(fieldName, index) {
  return `
    <div class="cn-repeater-item" data-index="${index}">
      <div class="cn-form-field">
        <label>Label</label>
        <input type="text" name="${fieldName}[${index}][label]" class="cn-input cn-input-small" placeholder="e.g., Portfolio, GitHub, Twitter">
      </div>
      <div class="cn-form-field">
        <label>URL</label>
        <input type="url" name="${fieldName}[${index}][url]" class="cn-input cn-input-small" placeholder="https://example.com">
      </div>
      <button type="button" class="cn-btn cn-btn-small cn-btn-outline cn-remove-item">Remove</button>
    </div>
  `;
}
