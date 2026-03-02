const webp = require('webp-converter');
const fs = require('fs');
const path = require('path');

// Source and destination folders
const INPUT_DIR = './assets/images/home/city/';
const OUTPUT_DIR = './output_webp';

// Ensure permission and output directory exists
webp.grant_permission();
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR);
}

// Read files in the input directory
fs.readdir(INPUT_DIR, (err, files) => {
  if (err) {
    return console.error('Error reading input directory:', err);
  }

  files.forEach(file => {
    const ext = path.extname(file).toLowerCase();
    // Check for .jpg, .jpeg, or .png extensions
    if (ext === '.jpg' || ext === '.jpeg' || ext === '.png') {
      const inputPath = path.join(INPUT_DIR, file);
      // Change the extension for the output file name
      const outputFileName = path.parse(file).name + '.webp';
      const outputPath = path.join(OUTPUT_DIR, outputFileName);

      // Perform the conversion with quality 80
      webp.cwebp(inputPath, outputPath, "-q 80")
        .then(() => console.log(`Converted ${file} to ${outputFileName}`))
        .catch(error => console.error(`Error converting ${file}:`, error));
    }
  });
});
