<template>
  <div class="pa-4">
    <div class="d-flex align-center mb-4">
      <v-select :items="modes" v-model="mode" label="Mode" dense outlined style="max-width:220px"/>
      <v-text-field v-model.number="count" type="number" min="1" max="50" class="ml-4" label="Count" dense outlined style="max-width:120px"/>
      <v-btn color="primary" class="ml-4" @click="start">Start</v-btn>
      <div class="ml-auto" v-if="started">Score: {{ correct }}/{{ index }}</div>
    </div>

    <v-card v-if="started && current" class="pa-4">
      <div class="text-h6 mb-2" v-if="mode==='multiple_choice'">Select the correct meaning</div>
      <div class="text-h6 mb-2" v-else>Type the missing word</div>
      <div class="mb-4"><b>{{ prompt }}</b></div>
      <div v-if="mode==='multiple_choice'">
        <v-btn v-for="c in current.choices" :key="c" class="mr-2 mb-2" :color="answered && c===current.answer ? 'success' : ''" @click="choose(c)" :disabled="answered">{{ c }}</v-btn>
      </div>
      <div v-else>
        <v-text-field v-model="typed" label="Your answer" dense outlined :disabled="answered" @keyup.enter="submitCloze"/>
        <v-btn color="primary" @click="submitCloze" :disabled="answered">Submit</v-btn>
      </div>
      <div class="mt-3" v-if="answered">
        <v-chip small :color="lastCorrect ? 'success' : 'error'" text-color="white">{{ lastCorrect ? 'Correct' : 'Wrong' }}</v-chip>
        <span class="ml-2">Answer: {{ current.answer }}</span>
        <v-btn small class="ml-4" @click="next">Next</v-btn>
      </div>
    </v-card>

    <v-alert type="info" border="left" class="mt-4" v-if="finished">Finished! Score: {{ correct }}/{{ items.length }}</v-alert>
  </div>
</template>

<script>
export default {
  data: () => ({
    modes: [
      { text:'Multiple choice', value:'multiple_choice' },
      { text:'Cloze', value:'cloze' },
    ],
    mode: 'multiple_choice',
    count: 10,
    items: [],
    index: 0,
    correct: 0,
    answered: false,
    lastCorrect: false,
    typed: '',
    started: false,
    finished: false,
  }),
  computed: {
    current() { return this.items[this.index] || null; },
    prompt() { return this.current ? this.current.prompt : ''; }
  },
  methods: {
    start() {
      this.started = true; this.finished = false; this.index = 0; this.correct = 0; this.answered = false; this.typed='';
      axios.get('/reviews/games', { params: { type: this.mode, count: this.count }})
        .then(r => { this.items = r.data.items || []; })
        .catch(() => { this.items = []; this.finished=true; this.started=false; });
    },
    choose(c) {
      if (this.answered) return;
      this.answered = true;
      this.lastCorrect = (c === this.current.answer);
      if (this.lastCorrect) this.correct++;
    },
    submitCloze() {
      if (this.answered) return;
      const a = (this.typed||'').trim().toLowerCase();
      const correct = (this.current.answer||'').trim().toLowerCase();
      this.answered = true;
      this.lastCorrect = (a === correct);
      if (this.lastCorrect) this.correct++;
    },
    next() {
      this.index++;
      this.answered = false; this.lastCorrect = false; this.typed='';
      if (this.index >= this.items.length) { this.finished = true; this.started=false; }
    }
  }
}
</script>

