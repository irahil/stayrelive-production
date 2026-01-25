const webp = require('webp-converter');
const fs = require('fs');
const path = require('path');

const inputDir = path.join(__dirname, 'assets/images');
const outputDir = path.join(inputDir, 'webp');

if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
}

fs.readdirSync(inputDir).forEach(file => {
    if (/\.(jpe?g|png)$/i.test(file)) {
        const inputFile = path.join(inputDir, file);
        const outputFile = path.join(outputDir, file.replace(/\.(jpe?g|png)$/i, '.webp'));
        webp.cwebp(inputFile, outputFile, "-q 80", (status, error) => {
            if (status === '100') {
                console.log(`Converted: ${file} -> ${outputFile}`);
            } else {
                console.error(`Error converting ${file}:`, error);
            }
        });
    }
});