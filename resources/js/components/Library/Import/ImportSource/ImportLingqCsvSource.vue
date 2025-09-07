<template>
  <div class="d-flex flex-column align-stretch">
    <label class="font-weight-bold">LingQ CSV</label>
    <input ref="file" type="file" accept=".csv,text/csv" @change="onFile" />
    <small class="mt-2">Tip: Export from LingQ as CSV and upload here. We will extract the text/context and prepare it for import.</small>
  </div>
</template>

<script>
export default {
  methods: {
    onFile(e) {
      const f = e.target.files && e.target.files[0];
      if (!f) {
        this.$emit('text-selected', { text: '', isImportSourceValid: false });
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const text = reader.result || '';
          const rows = this.parseCsv(String(text));
          if (!rows.length) throw new Error('Empty CSV');
          // Try common LingQ headers
          const headers = rows[0].map((h) => h.trim().toLowerCase());
          const idxContext = headers.indexOf('context') >= 0 ? headers.indexOf('context') : headers.indexOf('sentence');
          const idxText = headers.indexOf('text');
          const body = rows.slice(1)
            .map((r) => {
              if (idxText >= 0 && r[idxText]) return r[idxText];
              if (idxContext >= 0 && r[idxContext]) return r[idxContext];
              return '';
            })
            .filter(Boolean)
            .join('\n\n');
          this.$emit('text-selected', { text: body, isImportSourceValid: body.length > 0 });
        } catch (err) {
          this.$emit('text-selected', { text: '', isImportSourceValid: false });
        }
      };
      reader.readAsText(f);
    },
    // Simple CSV parser that honors quotes
    parseCsv(str) {
      const out = [];
      let row = [];
      let cur = '';
      let inQuotes = false;
      for (let i = 0; i < str.length; i++) {
        const c = str[i];
        if (inQuotes) {
          if (c === '"') {
            if (str[i + 1] === '"') { cur += '"'; i++; }
            else { inQuotes = false; }
          } else { cur += c; }
        } else {
          if (c === '"') inQuotes = true;
          else if (c === ',') { row.push(cur); cur = ''; }
          else if (c === '\n') { row.push(cur); out.push(row); row = []; cur = ''; }
          else if (c === '\r') { /* ignore */ }
          else { cur += c; }
        }
      }
      if (cur.length || row.length) { row.push(cur); out.push(row); }
      return out;
    }
  }
}
</script>

