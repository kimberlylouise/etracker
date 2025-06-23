document.addEventListener("DOMContentLoaded", function () {
  // Fetch evaluation summary and program-specific evaluations
  fetch('get_evaluations.php')
    .then(res => res.json())
    .then(data => {
      // Overview
      document.getElementById('total-evals').textContent = data.total_evaluations;

      // Table
      const tbody = document.getElementById('eval-table-body');
      tbody.innerHTML = '';
      data.programs.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${row.program_name}</td>
          <td>${row.status}</td>
          <td>${row.submitted_date || ''}</td>
          <td>
            ${row.can_evaluate
              ? `<button class="eval-btn" data-pid="${row.program_id}" data-pname="${row.program_name}">Evaluate</button>`
              : `<button class="evaluated-btn" disabled>Evaluated</button>`
            }
          </td>
        `;
        tbody.appendChild(tr);
      });

      // Button event
      document.querySelectorAll('.eval-btn').forEach(btn => {
        btn.onclick = function() {
          openDetailedEvalModal(
            btn.getAttribute('data-pid'),
            btn.getAttribute('data-pname')
          );
        };
      });
    });

  // Show the detailed evaluation modal
  function openDetailedEvalModal(programId, programName) {
    document.getElementById('detailed-program-id').value = programId;
    document.getElementById('modal-program-title').textContent = programName;
    document.getElementById('detailed-eval-form').reset();
    document.getElementById('detailed-eval-message').textContent = '';
    document.getElementById('detailed-eval-modal').style.display = 'block';
  }

  // Close modal logic
  document.getElementById('close-detailed-eval-modal').onclick = function() {
    document.getElementById('detailed-eval-modal').style.display = 'none';
  };

  // Close when clicking outside the modal content
  document.getElementById('detailed-eval-modal').onclick = function(event) {
    if (event.target === this) {
      this.style.display = 'none';
    }
  };

  // Attach to Evaluate buttons (after you render the table)
  document.querySelectorAll('.eval-btn').forEach(btn => {
    btn.onclick = function() {
      openDetailedEvalModal(
        btn.getAttribute('data-pid'),
        btn.getAttribute('data-pname')
      );
    };
  });

  document.getElementById('detailed-eval-form').onsubmit = function(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
      program_id: form.program_id.value,
      content: form.content.value,
      facilitators: form.facilitators.value,
      relevance: form.relevance.value,
      organization: form.organization.value,
      experience: form.experience.value,
      suggestion: form.suggestion.value,
      recommend: Array.from(form.recommend)
        .filter(cb => cb.checked)
        .map(cb => cb.value)
        .join(', ')
    };
    fetch('submit_detailed_evaluation.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resp => {
      document.getElementById('detailed-eval-message').textContent = resp.message;
      // After successful submit
      if (resp.status === 'success') {
        setTimeout(() => {
          document.getElementById('detailed-eval-modal').style.display = 'none';
          location.reload(); // This will reload and update the table
        }, 1000);
      }
    });
  };

  document.getElementById('view-all-evals').onclick = function(e) {
  e.preventDefault();
  fetch('get_all_evaluations.php')
    .then(res => res.json())
    .then(data => {
      console.log(data);
      const section = document.querySelector('.all-evals-section');
      const tbody = section.querySelector('#all-evals-table-body');
      tbody.innerHTML = '';
      data.evaluations.forEach(ev => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${ev.program_name}</td>
          <td>${ev.eval_date || ''}</td>
          <td>${ev.content}</td>
          <td>${ev.facilitators}</td>
          <td>${ev.relevance}</td>
          <td>${ev.organization}</td>
          <td>${ev.experience}</td>
          <td>${ev.suggestion || ''}</td>
          <td>${ev.recommend || ''}</td>
        `;
        tbody.appendChild(tr);
      });
      section.style.display = 'block'; // Show the section
    });
};
  // Close modal logic
  document.getElementById('close-all-evals-modal').onclick = function() {
    document.getElementById('all-evals-modal').style.display = 'none';
  };
  // Close when clicking outside the modal content
  document.getElementById('all-evals-modal').onclick = function(event) {
    if (event.target === this) {
      this.style.display = 'none';
    }
  };
});