<?php
require_once 'templates/includes/header.php';

// only logged-in users can create posts
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>

<div class="form-container">
    <h2>Create a New Blog Post</h2>

    <?php
    // Display any error messages
    if (isset($_SESSION['error_message'])) {
        echo '<div class="error-message">' . $_SESSION['error_message'] . '</div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <form action="posts/handle_create_post.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>

        <div class="form-group">
            <label for="editor">Content</label>

            <!-- Toolbar -->
            <div class="toolbar">
                <button type="button" onclick="formatText('bold')"><b>B</b></button>
                <button type="button" onclick="formatText('italic')"><i>I</i></button>
                <button type="button" onclick="formatText('underline')"><u>U</u></button>
                <button type="button" onclick="formatText('foreColor', prompt('Enter a color:'))">Color</button>
                <button type="button" onclick="formatText('justifyLeft')">Left</button>
                <button type="button" onclick="formatText('justifyCenter')">Center</button>
                <button type="button" onclick="formatText('justifyRight')">Right</button>
                <button type="button" onclick="insertImage()">Image</button>
            </div>

            <!-- Editable div -->
            <div id="editor" contenteditable="true" class="editable-div"></div>

            <!-- Hidden input for submitted content -->
            <input type="hidden" name="content" id="hidden-content">

            <!-- Hidden file input for image upload -->
            <input type="file" id="image-upload" style="display:none;" onchange="uploadImage(event)">
        </div>

        <div class="form-group">
            <label for="hashtags-input">Hashtags</label>

            <div id="tag-container" class="tag-container">
                <input type="text" id="hashtags-input" placeholder="Type and press Enter..." list="tagSuggestions" autocomplete="off">
                <datalist id="tagSuggestions"></datalist>
            </div>

            <div id="suggestions-box" class="suggestions-box"></div>
            <input type="hidden" name="hashtags" id="hidden-hashtags-input">
            <small>Add tags by typing and pressing Enter or comma. Click a tag to remove it.</small>
        </div>

        <button type="submit" class="btn">Publish Post</button>
    </form>
</div>

<script>
// === Text editor ===
function formatText(command, value = null) {
    document.execCommand(command, false, value);
}

function insertImage() {
    document.getElementById('image-upload').click();
}

function uploadImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);

    fetch('posts/upload_image.php', { 
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(url => formatText('insertImage', url))
    .catch(err => alert('Image upload failed: ' + err));
}

// Copy editor content to hidden input before submission
const form = document.querySelector('form');
form.addEventListener('submit', function() {
    document.getElementById('hidden-content').value = document.getElementById('editor').innerHTML;
});

// === Tag suggestions + local tag management ===
(function() {
  const input = document.getElementById('hashtags-input');
  const datalist = document.getElementById('tagSuggestions');
  const hiddenInput = document.getElementById('hidden-hashtags-input');
  const tagContainer = document.getElementById('tag-container');
  let tags = [];

  // Add tag on Enter or comma
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const tag = input.value.trim().replace(/,$/, '');
      if (tag && !tags.includes(tag)) {
        tags.push(tag);
        renderTags();
      }
      input.value = '';
    }
  });

  // Render tags visually
  function renderTags() {
    // Keep the input as the last child
    const currentInput = input;
    tagContainer.innerHTML = '';
    tags.forEach(function(tag) {
      const span = document.createElement('span');
      span.className = 'tag';
      span.textContent = '#' + tag;
      span.onclick = function() {
        tags = tags.filter(t => t !== tag);
        renderTags();
      };
      tagContainer.appendChild(span);
    });
    tagContainer.appendChild(currentInput);
    hiddenInput.value = tags.join(',');
  }

  // === Autocomplete suggestions ===
  let ctrl = null;
  function fetchTags(term) {
    if (ctrl) ctrl.abort();
    ctrl = new AbortController();
    const url = 'api/get_hashtags.php?q=' + encodeURIComponent(term || '');
    fetch(url, { signal: ctrl.signal, headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        if (!data || !data.ok) return;
        datalist.innerHTML = '';
        data.items.forEach(function(name) {
          const opt = document.createElement('option');
          opt.value = name;
          datalist.appendChild(opt);
        });
      })
      .catch(() => {});
  }

  // Fetch top tags on load
  fetchTags('');

  input.addEventListener('input', function() {
    const term = input.value.split(',').pop().trim();
    if (term.length >= 1) fetchTags(term);
  });
})();
</script>

<style>
.editable-div {
    border: 1px solid #ccc;
    min-height: 200px;
    padding: 10px;
    margin-top: 5px;
    overflow-y: auto;
}

.toolbar {
    margin-bottom: 5px;
}

.toolbar button {
    margin-right: 5px;
    padding: 5px 8px;
    cursor: pointer;
}

.tag-container {
    border: 1px solid #ccc;
    min-height: 40px;
    padding: 5px;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    cursor: text;
}

.tag {
    background-color: #e0e0e0;
    border-radius: 3px;
    padding: 2px 6px;
    display: inline-block;
}

.suggestions-box {
    display: none; /* datalist handles dropdown */
}
</style>

<?php
require_once 'templates/includes/footer.php';
?>
