(() => {
  const root = document.querySelector('#story-reading-preview');
  if (!root) return;

  const stories = JSON.parse(root.dataset.stories || '[]');
  const form = root.querySelector('[data-preview-form]');
  const storySelect = root.querySelector('[data-story]');
  const sceneSelect = root.querySelector('[data-scene]');
  const direction = root.querySelector('[data-scene-direction]');
  const originalCharacters = root.querySelector('[data-original-characters]');
  const previewCharacters = root.querySelector('[data-preview-characters]');
  const button = root.querySelector('[data-render-button]');
  const status = root.querySelector('[data-result-status]');
  const audio = root.querySelector('[data-audio]');
  let audioUrl = null;

  const selectedStory = () => stories.find((story) => story.id === storySelect.value);
  const selectedScene = () => selectedStory()?.scenes.find((scene) => scene.id === sceneSelect.value);

  const updateSceneDetails = () => {
    const scene = selectedScene();
    if (!scene) return;
    direction.textContent = scene.direction || 'Keine zusätzliche Szenenanweisung.';
    originalCharacters.textContent = `${scene.originalCharacters.toLocaleString('de-DE')} Zeichen`;
    previewCharacters.textContent = `${scene.previewCharacters.toLocaleString('de-DE')} Zeichen${scene.truncated ? ' (gekürzter Preview-Ausschnitt)' : ''}`;
    button.textContent = `Kostenpflichtige Vorschau erzeugen (${scene.previewCharacters.toLocaleString('de-DE')} Zeichen)`;
  };

  const updateScenes = () => {
    const story = selectedStory();
    sceneSelect.replaceChildren(...(story?.scenes || []).map((scene) => new Option(scene.title, scene.id)));
    updateSceneDetails();
  };

  storySelect.replaceChildren(...stories.map((story) => new Option(story.title, story.id)));
  storySelect.addEventListener('change', updateScenes);
  sceneSelect.addEventListener('change', updateSceneDetails);
  updateScenes();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    button.disabled = true;
    status.textContent = 'ElevenLabs erzeugt die Vorschau …';
    audio.hidden = true;

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'audio/mpeg, application/json' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        const contentType = response.headers.get('content-type') || '';
        const error = contentType.includes('application/json') ? (await response.json()).error : await response.text();
        throw new Error(error || `HTTP ${response.status}`);
      }

      const blob = await response.blob();
      if (audioUrl) URL.revokeObjectURL(audioUrl);
      audioUrl = URL.createObjectURL(blob);
      audio.src = audioUrl;
      audio.hidden = false;
      const cost = response.headers.get('x-elevenlabs-character-cost');
      status.textContent = cost
        ? `Vorschau fertig. ElevenLabs meldet ${Number(cost).toLocaleString('de-DE')} berechnete Zeichen.`
        : 'Vorschau fertig.';
      await audio.play().catch(() => {});
    } catch (error) {
      status.textContent = `Vorschau fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`;
    } finally {
      button.disabled = false;
    }
  });
})();
