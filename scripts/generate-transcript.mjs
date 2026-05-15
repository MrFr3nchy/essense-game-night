import { readFileSync, writeFileSync } from 'fs';

const JSONL_PATH = '/Users/justinfrench/.cursor/projects/Users-justinfrench-Development-vote-on-games-challenge/agent-transcripts/3a0486a7-20f5-4007-9351-076bf0a03427/3a0486a7-20f5-4007-9351-076bf0a03427.jsonl';
const OUT_PATH = new URL('../ui/public/transcript.md', import.meta.url).pathname;

const rawLines = readFileSync(JSONL_PATH, 'utf-8').trim().split('\n');

function extractUserText(content) {
  const raw = content.filter(c => c.type === 'text').map(c => c.text).join('\n');
  const match = raw.match(/<user_query>([\s\S]*?)<\/user_query>/);
  return match ? match[1].trim() : raw.trim();
}

/**
 * For assistant messages, pick the best single text block to display.
 * Strategy: prefer the last long text block (post-tool-use responses tend to come last),
 * skip blocks that look like pure internal planning monologue.
 */
function extractAssistantText(content) {
  const texts = content
    .filter(c => c.type === 'text')
    .map(c => c.text.trim())
    .filter(t => t.length > 40);

  if (texts.length === 0) return '';

  // Heuristic: if there are multiple blocks, prefer the last one that starts
  // with a capital letter and looks user-facing (not raw planning notes).
  for (let i = texts.length - 1; i >= 0; i--) {
    const t = texts[i];
    // Skip blocks that are clearly internal planning (start with lowercase, very long run-ons)
    if (/^[A-Z🎮🎲✅❌⚠️]/.test(t)) return t;
  }
  return texts[texts.length - 1];
}

// Parse all events into a flat list
const events = [];
for (const line of rawLines) {
  let parsed;
  try { parsed = JSON.parse(line); } catch { continue; }
  const { role, message } = parsed;
  if (!message?.content) continue;
  events.push({ role, content: message.content });
}

// Group into turns: each turn = one user message + all following assistant messages until the next user message
const turns = [];
let current = null;
for (const ev of events) {
  if (ev.role === 'user') {
    if (current) turns.push(current);
    const text = extractUserText(ev.content);
    if (text) current = { user: text, assistant: [] };
  } else if (ev.role === 'assistant' && current) {
    const text = extractAssistantText(ev.content);
    const tools = [...new Set(ev.content.filter(c => c.type === 'tool_use').map(c => c.name))];
    if (text || tools.length > 0) {
      current.assistant.push({ text, tools });
    }
  }
}
if (current) turns.push(current);

// Merge consecutive assistant entries in each turn into one clean response
function buildAssistantBlock(entries) {
  // Find the entry with the most useful text (last substantial response)
  let bestText = '';
  const allTools = new Set();
  for (const e of entries) {
    if (e.text && e.text.length > bestText.length) bestText = e.text;
    e.tools.forEach(t => allTools.add(t));
  }
  return { text: bestText, tools: [...allTools] };
}

const out = [
  '# Build Log — Game Night App',
  '',
  '> A condensed transcript of the AI-assisted session in [Cursor](https://cursor.com) that built this app from scratch.',
  '',
  '---',
  '',
];

turns.forEach((turn, i) => {
  out.push(`## Prompt ${i + 1}`);
  out.push('');
  // Quote user message, preserving line breaks
  turn.user.split('\n').forEach(line => out.push(`> ${line}`));
  out.push('');

  const { text, tools } = buildAssistantBlock(turn.assistant);

  if (text) {
    out.push(text);
    out.push('');
  }

  if (tools.length > 0) {
    const labels = {
      Write: '📝 Write file',
      Read: '📖 Read file',
      Shell: '⚙️ Run command',
      StrReplace: '✏️ Edit file',
      Glob: '🔍 Find files',
      Grep: '🔍 Search code',
      WebFetch: '🌐 Fetch URL',
      WebSearch: '🌐 Web search',
      TodoWrite: '📋 Update tasks',
      SemanticSearch: '🔍 Semantic search',
      ReadLints: '🔎 Check lints',
      Delete: '🗑️ Delete file',
      SwitchMode: '🔄 Switch mode',
      AskQuestion: '❓ Ask question',
      CallMcpTool: '🖥️ Browser action',
    };
    const mapped = tools.map(t => labels[t] ?? t);
    out.push(`*${mapped.join(' · ')}*`);
    out.push('');
  }

  out.push('---');
  out.push('');
});

writeFileSync(OUT_PATH, out.join('\n'), 'utf-8');
console.log(`Written to ${OUT_PATH} (${turns.length} turns)`);
