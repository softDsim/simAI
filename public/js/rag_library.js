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

        // Markiert den aktuell aktiven Tag im Dropdown
        const isProf = doc.tag === 'professor' ? 'selected' : '';
        const isStud = doc.tag === 'student' ? 'selected' : '';

        tbody.innerHTML += `
            <tr class="rag-row" style="border-bottom: 1px solid var(--border-stroke-hairline);">
                <td style="padding: 0.5rem;"><input type="checkbox" class="rag-checkbox" value="${doc.uuid}"></td>
                <td style="padding: 0.5rem;">
                    <span class="rag-title" id="title-${doc.uuid}">${doc.title}</span>
                </td>
                <td style="padding: 0.5rem;">
                    <!-- Neues Tag Dropdown -->
                    <select onchange="changeRagTag('${doc.uuid}', this.value)" style="background: transparent; border: 1px solid var(--border-stroke-strong); border-radius: 4px; color: var(--text-color); padding: 4px 6px; font-size: 0.85rem; cursor: pointer; outline: none;">
                        <option value="professor" style="color: black;" ${isProf}>professor</option>
                        <option value="student" style="color: black;" ${isStud}>student</option>
                    </select>
                </td>
                <td style="padding: 0.5rem;">${date}</td>
                <td style="padding: 0.5rem;">
                    <button class="btn-xs" onclick="renameRagDoc('${doc.uuid}')" style="display:inline-flex; border: 1px solid var(--border-stroke-strong); border-radius: 4px; padding: 4px 8px;">
                        ✏️ Umbenennen
                    </button>
                </td>
            </tr>
        `;
    });
}

// NEU: Ruft das Backend auf, wenn sich das Dropdown ändert
async function changeRagTag(uuid, newTag) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(`/req/rag-library/${uuid}/tag`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ tag: newTag })
        });

        if(!response.ok) throw new Error('Fehler beim Tag Update');
        // Erfolgreich geändert! Qdrant und MySQL sind nun synchron.
    } catch(e) {
        console.error(e);
        alert("Tag konnte nicht geändert werden.");
        loadRagLibrary(); // Lädt alte Werte, falls es fehlschlägt
    }
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
