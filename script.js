const classData = {
  krijger: {
    name: "Krijger",
    kicker: "De standvastige voorhoede",
    description: "Meester van wapen en harnas. De krijger houdt de linie, beschermt het gezelschap en blijft staan wanneer anderen moeten wijken.",
    health: "5",
    armour: "Alle harnassen",
    equipment: "Alle wapens + schild",
    gold: "125",
    quote: "\"Achter mij. Ik vang de eerste slag op.\"",
    image: "img/Krijger.png",
    alt: "Krijger in volledig harnas met zwaard en schild"
  },
  magier: {
    name: "Magier",
    kicker: "De geleerde van het onmogelijke",
    description: "Kwetsbaar van lichaam, verwoestend in kracht. De magier verandert het slagveld met vuur, bescherming en listige verplaatsingen.",
    health: "3",
    armour: "Geen harnas",
    equipment: "Mes, darts en staf",
    gold: "100",
    quote: "\"De werkelijkheid is slechts een voorstel.\"",
    image: "img/Magier.png",
    alt: "Magier in lange gewaden met een staf"
  },
  dief: {
    name: "Dief",
    kicker: "De schaduw tussen twee stappen",
    description: "Sluipt waar anderen stormen, opent wat gesloten hoort te blijven en treft vijanden precies wanneer hun aandacht elders ligt.",
    health: "4",
    armour: "Licht harnas",
    equipment: "Mes, rapier, kortzwaard, kruisboog",
    gold: "100",
    quote: "\"Een slot is gewoon een vraag met een stil antwoord.\"",
    image: "img/Dief.png",
    alt: "Dief in donkere lederen kleding met dolken"
  },
  priester: {
    name: "Priester",
    kicker: "De stem van het hogere",
    description: "Genezer, beschermer en vijand van het duister. De priester houdt bondgenoten op de been en geeft geloof een tastbare kracht.",
    health: "4",
    armour: "Licht/medium",
    equipment: "Knotsen, bijl, kruisboog + schild",
    gold: "125",
    quote: "\"Zolang het licht antwoordt, is niemand verloren.\"",
    image: "img/Priester.png",
    alt: "Priester in gewaden met een strijdknots"
  },
  barbaar: {
    name: "Barbaar",
    kicker: "De ontembare storm",
    description: "Ruw, direct en bijna niet te stoppen. De barbaar ontketent zijn woede en werpt zich zonder aarzeling in het hart van het gevecht.",
    health: "6",
    armour: "Pelsen/bont",
    equipment: "Slagwapens, geen Afstands-wapens, beukelaar",
    gold: "60",
    quote: "\"Geen muur blijft staan als je hard genoeg brult.\"",
    image: "img/Barbaar.png",
    alt: "Barbaar met bont en zware wapens"
  },
  bard: {
    name: "Bard",
    kicker: "De vonk die helden doet opstaan",
    description: "Met woorden, muziek en magie stuurt de bard vriend en vijand. Inspiratie kan een gezelschap net zo krachtig maken als staal.",
    health: "4",
    armour: "Licht harnas",
    equipment: "Dolk, rapier, zwaard, kruisboog",
    gold: "125",
    quote: "\"Een goed verhaal wint het gevecht voor de eerste slag.\"",
    image: "img/Bard.png",
    alt: "Bard in rijk versierde kleding met een instrument"
  },
  paladijn: {
    name: "Paladijn",
    kicker: "Het schild met een eed",
    description: "Een geharnaste kampioen die kracht aan overtuiging koppelt. De paladijn beschermt, geneest en herkent het kwaad voor het toeslaat.",
    health: "5",
    armour: "Alle harnassen",
    equipment: "Alle wapens + schild",
    gold: "125",
    quote: "\"Mijn eed wijkt niet. Dus ik ook niet.\"",
    image: "img/Paladijn.png",
    alt: "Paladijn in glanzend harnas met een heilig symbool"
  }
};

const header = document.querySelector("[data-header]");
const navToggle = document.querySelector("[data-nav-toggle]");
const nav = document.querySelector("[data-nav]");

function closeNavigation() {
  document.body.classList.remove("nav-open");
  navToggle.setAttribute("aria-expanded", "false");
}

navToggle.addEventListener("click", () => {
  const open = document.body.classList.toggle("nav-open");
  navToggle.setAttribute("aria-expanded", String(open));
});

nav.querySelectorAll("a").forEach((link) => link.addEventListener("click", closeNavigation));

window.addEventListener("scroll", () => {
  header.classList.toggle("scrolled", window.scrollY > 30);
}, { passive: true });

const classPanel = document.querySelector("#class-panel");
const classTabs = document.querySelectorAll("[data-class]");
const classImage = document.querySelector("[data-class-image]");
const className = document.querySelector("[data-class-name]");
const classKicker = document.querySelector("[data-class-kicker]");
const classDescription = document.querySelector("[data-class-description]");
const classQuote = document.querySelector("[data-class-quote]");
const classHealth = document.querySelector("[data-class-health]");
const classArmour = document.querySelector("[data-class-armour]");
const classEquipment = document.querySelector("[data-class-equipment]");
const classGold = document.querySelector("[data-class-gold]");

function selectClass(key) {
  const selected = classData[key];
  if (!selected) return;

  classPanel.classList.add("changing");

  window.setTimeout(() => {
    className.textContent = selected.name;
    classKicker.textContent = selected.kicker;
    classDescription.textContent = selected.description;
    classQuote.textContent = selected.quote;
    classImage.src = selected.image;
    classImage.alt = selected.alt;
    classHealth.textContent = selected.health;
    classArmour.textContent = selected.armour;
    classEquipment.textContent = selected.equipment;
    classGold.textContent = selected.gold;

    classTabs.forEach((tab) => {
      const active = tab.dataset.class === key;
      tab.setAttribute("aria-selected", String(active));
      tab.setAttribute("tabindex", active ? "0" : "-1");
    });
    classPanel.setAttribute("aria-labelledby", `tab-${key}`);
    classPanel.classList.remove("changing");
  }, 180);
}

classTabs.forEach((tab, index) => {
  tab.addEventListener("click", () => selectClass(tab.dataset.class));
  tab.addEventListener("keydown", (event) => {
    if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
    event.preventDefault();

    let nextIndex = index;
    if (event.key === "ArrowLeft") nextIndex = (index - 1 + classTabs.length) % classTabs.length;
    if (event.key === "ArrowRight") nextIndex = (index + 1) % classTabs.length;
    if (event.key === "Home") nextIndex = 0;
    if (event.key === "End") nextIndex = classTabs.length - 1;

    classTabs[nextIndex].focus();
    selectClass(classTabs[nextIndex].dataset.class);
  });
});

const revealObserver = new IntersectionObserver((entries, observer) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    entry.target.classList.add("visible");
    observer.unobserve(entry.target);
  });
}, { threshold: 0.12 });

document.querySelectorAll(".reveal").forEach((element) => revealObserver.observe(element));

document.querySelector("[data-year]").textContent = new Date().getFullYear();
