// js/main.js
function addExample() {
    const container = document.getElementById('examples-container');
    const div = document.createElement('div');
    div.className = 'example-item';
    div.innerHTML = `
        <input type="text" name="examples[sentence][]" placeholder="例句" style="width:70%">
        <input type="text" name="examples[source][]" placeholder="来源" style="width:25%">
        <button type="button" onclick="this.parentElement.remove()">删除</button>
    `;
    container.appendChild(div);
}

function addSynonym() {
    const container = document.getElementById('synonyms-container');
    const div = document.createElement('div');
    div.className = 'synonym-item';
    div.innerHTML = `
        <input type="text" name="synonyms[synonym][]" placeholder="同义词">
        <input type="text" name="synonyms[nuance][]" placeholder="细微差别">
        <button type="button" onclick="this.parentElement.remove()">删除</button>
    `;
    container.appendChild(div);
}