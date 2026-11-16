#!/usr/bin/env node
// PreToolUse (Bash): rejects `cat > file` / `cat >> file` when cat gets no input from a heredoc (<<), an input redirect
// (<) or a pipe. Without input, cat waits for the keyboard forever and the rest of the command never runs (it happened
// in deliveries 1.1 and 1.3b). Exit code 2 blocks the call and sends stderr back to Claude.
'use strict';

let raw = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
  raw += chunk;
});
process.stdin.on('end', () => {
  let command = '';
  try {
    command = JSON.parse(raw).tool_input?.command ?? '';
  } catch {
    process.exit(0); // Not our business: let the call through.
  }

  if (findBlockingCat(command) !== null) {
    process.stderr.write(
      'Comando rechazado: "cat >" o "cat >>" sin entrada (heredoc << o tubería) espera el teclado para siempre y ' +
        'el resto del comando nunca corre. Usá el editor (Edit/Write) o escribí el archivo en el scratchpad; si ' +
        'necesitás cat, dale la entrada con un heredoc (cat > archivo <<\'EOF\') o una tubería.\n',
    );
    process.exit(2);
  }
  process.exit(0);
});

/** Drops heredoc bodies, so a "cat >" written as text inside one is not taken for a command. */
function stripHeredocBodies(command) {
  const lines = command.split('\n');
  const out = [];
  let delimiter = null;
  for (const line of lines) {
    if (delimiter !== null) {
      if (line.replace(/^\t+/, '').trim() === delimiter) delimiter = null;
      continue;
    }
    out.push(line);
    const match = /<<-?\s*(['"]?)([A-Za-z_][A-Za-z0-9_]*)\1/.exec(line);
    if (match) delimiter = match[2];
  }
  return out.join('\n');
}

/** The first pipeline stage that runs `cat` with only options before a > or >> and no input, or null. */
function findBlockingCat(command) {
  const catWrite = /(^|[\s;'"(`{])cat((\s+-\S*)*)\s*\d?>>?/;
  const segments = stripHeredocBodies(command)
    .replace(/\|\|/g, ';')
    .split(/;|&&|&|\n/);
  for (const segment of segments) {
    const stages = segment.split(/\|&?/);
    for (let i = 0; i < stages.length; i++) {
      const stage = stages[i];
      if (!catWrite.test(stage)) continue;
      const fedByPipe = i > 0;
      const fedByRedirect = /<(?!\()/.test(stage); // <<, <<< or < file; not process substitution <(...)
      if (!fedByPipe && !fedByRedirect) return stage.trim();
    }
  }
  return null;
}
