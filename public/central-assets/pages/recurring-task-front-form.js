/* Central ARPYNET 2.39.1 — recurring-task-front-form */

(()=>{const org=document.getElementById('organization_id');const project=document.getElementById('project_id');const assignee=document.getElementById('assigned_to');if(!org||org.disabled)return;const filter=()=>{const id=org.value;project?.querySelectorAll('option[data-organization]').forEach(o=>o.disabled=o.dataset.organization!==id);assignee?.querySelectorAll('option[data-organizations]').forEach(o=>o.disabled=!o.dataset.organizations.split(',').includes(id));};org.addEventListener('change',filter);filter();})();
