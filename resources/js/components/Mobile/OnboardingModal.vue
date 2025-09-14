<template>
  <v-dialog v-model="open" max-width="520" persistent>
    <v-card class="rounded-xl pa-4">
      <v-card-title class="text-h6 font-weight-bold">{{ steps[step].title }}</v-card-title>
      <v-card-text>
        <div v-if="step===0">
          <div class="mb-2">Which language would you like to learn?</div>
          <v-select v-model="language" :items="languages" filled rounded dense></v-select>
        </div>
        <div v-else-if="step===1">
          <div class="mb-2">Tell us your level</div>
          <v-radio-group v-model="level">
            <v-radio label="Beginner" value="beginner"></v-radio>
            <v-radio label="Intermediate" value="intermediate"></v-radio>
            <v-radio label="Advanced" value="advanced"></v-radio>
          </v-radio-group>
        </div>
        <div v-else-if="step===2">
          <div class="mb-2 text-subtitle-1 font-weight-medium">Here's what you can achieve with LinguaCafe</div>
          <ul class="pl-6">
            <li>Build a deep vocabulary</li>
            <li>Become a fluent listener</li>
            <li>Make learning a habit</li>
          </ul>
        </div>
        <div v-else-if="step===3">
          <div class="mb-2">How much time can you commit daily?</div>
          <v-radio-group v-model="minutes">
            <v-radio label="10 min/day (Casual)" :value="10"/>
            <v-radio label="20 min/day (Steady)" :value="20"/>
            <v-radio label="40 min/day (Keen)" :value="40"/>
            <v-radio label="60 min/day (Intense)" :value="60"/>
          </v-radio-group>
        </div>
        <div v-else-if="step===4">
          <div class="mb-2">Pick topics you love</div>
          <v-chip-group v-model="interests" multiple column>
            <v-chip v-for="t in interestPool" :key="t" :value="t" class="ma-1" outlined>{{t}}</v-chip>
          </v-chip-group>
        </div>
        <div v-else>
          <div class="mb-2">Enable reminders?</div>
          <v-switch v-model="reminders" label="Daily reminder"> </v-switch>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-btn text rounded :disabled="step===0" @click="prev">Back</v-btn>
        <v-spacer></v-spacer>
        <v-btn color="primary" rounded depressed @click="next">{{ step===steps.length-1 ? 'Finish' : 'Continue' }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
export default {
  props: { value: Boolean, defaultLanguage: String },
  data: () => ({
    open: false,
    step: 0,
    steps: [
      { title: 'Welcome' },
      { title: 'Level' },
      { title: 'Get the most' },
      { title: 'Daily goal' },
      { title: 'Interests' },
      { title: 'Reminders' },
    ],
    languages: ['English','Spanish','Japanese','French','German','Italian','Korean','Portuguese','Chinese'],
    language: '',
    level: 'beginner',
    minutes: 20,
    interests: [],
    reminders: false,
    interestPool: ['Books','Business','Culture','Entertainment','Food','Grammar','Health','History','Kids','Language','Lifestyle','News','Podcasts','Pronunciation','Science','Songs'],
  }),
  watch: {
    value(v) { this.open = v; },
    open(v) { this.$emit('input', v); }
  },
  mounted() { this.open = !!this.value; this.language = this.defaultLanguage || 'English'; },
  methods: {
    prev() { this.step = Math.max(0, this.step-1); },
    next() {
      if (this.step < this.steps.length - 1) { this.step++; return; }
      const payload = { language: this.language, level: this.level, minutes: this.minutes, interests: this.interests, reminders: this.reminders };
      axios.post('/settings/user/update', { settings: { onboardingData: payload, onboardingCompleted: true }})
        .then(()=>{ this.open=false; this.$emit('completed', payload); })
        .catch(()=>{ this.open=false; });
    }
  }
}
</script>

<style scoped>
</style>

