window.addEventListener('DOMContentLoaded', async function (){
    loadRagLibrary();
});

async function loadRagLibrary() {
    try {
        const response = await fetch('/req/rag-library');
        if(!response.ok) throw new Error('Unberechtigt oder Netzwerkfehler');
        const docs = await response.json();
        renderRagDocs(docs);
    } catch(e) {
        console.error("Fehler beim Laden der Dokumente:", e);
    }
}

function renderRagDocs(docs) {
    const tbody = document.getElementById('rag-docs-list');
    tbody.innerHTML = '';
    docs.forEach(doc => {
        const date = new Date(doc.created_at).toLocaleDateString('de-DE');
        tbody.innerHTML += `
            <tr class="rag-row" style="border-bottom: var(--border-stroke-hairline);">
                <td style="padding: 1rem 0.5rem;"><input type="checkbox" class="rag-checkbox" value="${doc.uuid}"></td>
                <td style="padding: 1rem 0.5rem;">
                    <span class="rag-title" id="title-${doc.uuid}">${doc.title}</span>
                </td>
                <td style="padding: 1rem 0.5rem;">${doc.tag}</td>
                <td style="padding: 1rem 0.5rem;">${date}</td>
                <td style="padding: 1rem 0.5rem;">
                    <button class="btn-xs" onclick="renameRagDoc('${doc.uuid}')" style="display:inline-flex;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    </button>
                </td>
            </tr>
        `;
    });
}

async function renameRagDoc(uuid) {
    const span = document.getElementById(`title-${uuid}`);
    const newTitle = prompt("Neuen Titel eingeben:", span.innerText);
    if (!newTitle || newTitle === span.innerText) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    await fetch(`/req/rag-library/${uuid}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ title: newTitle })
    });
    span.innerText = newTitle;
}

async function deleteSelectedRagDocs() {
    const checkboxes = document.querySelectorAll('.rag-checkbox:checked');
    const uuids = Array.from(checkboxes).map(cb => cb.value);

    if (uuids.length === 0) return alert("Bitte wähle zuerst Dokumente aus!");
    if (!confirm(`${uuids.length} Dokument(e) endgültig aus der Datenbank löschen?`)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    await fetch('/req/rag-library/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ uuids: uuids })
    });

    loadRagLibrary();
}

function filterRagDocs() {
    const filter = document.getElementById('rag-search-input').value.toLowerCase();
    document.querySelectorAll('.rag-row').forEach(row => {
        const title = row.querySelector('.rag-title').innerText.toLowerCase();
        row.style.display = title.includes(filter) ? '' : 'none';
    });
}

function toggleSelectAllRag(source) {
    document.querySelectorAll('.rag-checkbox').forEach(cb => cb.checked = source.checked);
}
