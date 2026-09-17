#!/usr/bin/env node

const path = require('path');
const sharp = require('sharp');

const repoRoot = path.resolve(__dirname, '..');
const pixDir = path.join(repoRoot, 'moodle', 'local', 'nexusbranding', 'pix');
const sourceIcon = path.join(pixDir, 'site-icon.png');
const hero = path.join(pixDir, 'hero-01.png');
const faviconOutput = path.join(pixDir, 'site-icon-white-bg.png');
const socialOutput = path.join(pixDir, 'nexus-lms-social-cover.png');

const iconTile = Buffer.from(`
<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="6" width="500" height="500" rx="104" fill="#ffffff" stroke="#dbe4ee" stroke-width="12"/>
</svg>`);

const socialOverlay = Buffer.from(`
<svg width="1200" height="630" viewBox="0 0 1200 630" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="navy" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%" stop-color="#081d33" stop-opacity="0.98"/>
      <stop offset="46%" stop-color="#0b2946" stop-opacity="0.94"/>
      <stop offset="68%" stop-color="#102f4c" stop-opacity="0.52"/>
      <stop offset="100%" stop-color="#102f4c" stop-opacity="0.08"/>
    </linearGradient>
    <linearGradient id="bottom" x1="0" y1="0" x2="0" y2="1">
      <stop offset="55%" stop-color="#07192b" stop-opacity="0"/>
      <stop offset="100%" stop-color="#07192b" stop-opacity="0.82"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#navy)"/>
  <rect width="1200" height="630" fill="url(#bottom)"/>
  <rect x="80" y="204" width="58" height="7" rx="3.5" fill="#d6aa28"/>
  <text x="205" y="104" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="27" font-weight="700" letter-spacing="1.4">NEXUS EDUCATION PRIVATE SCHOOL</text>
  <text x="205" y="137" fill="#e6bd45" font-family="Arial, Helvetica, sans-serif" font-size="15" font-weight="700" letter-spacing="2">TORONTO, ONTARIO · SECURE DIGITAL LEARNING</text>
  <text x="80" y="283" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="66" font-weight="700" letter-spacing="0.2">Learning Portal</text>
  <text x="80" y="337" fill="#e8f1f7" font-family="Arial, Helvetica, sans-serif" font-size="24" font-weight="400">Course materials, assignments, quizzes, grades</text>
  <text x="80" y="371" fill="#e8f1f7" font-family="Arial, Helvetica, sans-serif" font-size="24" font-weight="400">and academic support in one secure place.</text>
  <g font-family="Arial, Helvetica, sans-serif" font-size="17" font-weight="700" fill="#ffffff">
    <rect x="80" y="417" width="154" height="38" rx="19" fill="#ffffff" fill-opacity="0.14" stroke="#ffffff" stroke-opacity="0.28"/>
    <text x="103" y="443">LEARN</text>
    <rect x="246" y="417" width="174" height="38" rx="19" fill="#ffffff" fill-opacity="0.14" stroke="#ffffff" stroke-opacity="0.28"/>
    <text x="269" y="443">PROGRESS</text>
    <rect x="432" y="417" width="166" height="38" rx="19" fill="#ffffff" fill-opacity="0.14" stroke="#ffffff" stroke-opacity="0.28"/>
    <text x="455" y="443">SUCCEED</text>
  </g>
  <text x="80" y="554" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="22" font-weight="700">lms.nexuseps.com</text>
  <text x="80" y="584" fill="#d1deea" font-family="Arial, Helvetica, sans-serif" font-size="15" font-weight="400">Authorized access for Nexus students, teachers and school administration</text>
</svg>`);

async function build() {
  const crest = await sharp(sourceIcon)
    .resize(390, 390, { fit: 'contain' })
    .png()
    .toBuffer();

  await sharp({
    create: { width: 512, height: 512, channels: 4, background: { r: 255, g: 255, b: 255, alpha: 0 } },
  })
    .composite([
      { input: iconTile, left: 0, top: 0 },
      { input: crest, left: 61, top: 61 },
    ])
    .png({ compressionLevel: 9 })
    .toFile(faviconOutput);

  const socialIcon = await sharp(faviconOutput)
    .resize(104, 104, { fit: 'contain' })
    .png()
    .toBuffer();

  await sharp(hero)
    .resize(1200, 630, { fit: 'cover', position: 'east' })
    .modulate({ saturation: 0.9, brightness: 0.96 })
    .composite([
      { input: socialOverlay, left: 0, top: 0 },
      { input: socialIcon, left: 80, top: 58 },
    ])
    .png({ compressionLevel: 9, palette: true, quality: 94 })
    .toFile(socialOutput);

  const iconMeta = await sharp(faviconOutput).metadata();
  const socialMeta = await sharp(socialOutput).metadata();
  console.log(JSON.stringify({
    favicon: { path: faviconOutput, width: iconMeta.width, height: iconMeta.height },
    social: { path: socialOutput, width: socialMeta.width, height: socialMeta.height },
  }, null, 2));
}

build().catch((error) => {
  console.error(error);
  process.exit(1);
});
