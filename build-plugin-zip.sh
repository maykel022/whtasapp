#!/usr/bin/env bash
set -e

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

echo "📦 Packaging WordPress Plugin into wp-whatsapp-channel-autopublish.zip..."

rm -f wp-whatsapp-channel-autopublish.zip

zip -r wp-whatsapp-channel-autopublish.zip wp-whatsapp-channel-autopublish -x "*.DS_Store"

echo "✅ Created wp-whatsapp-channel-autopublish.zip successfully!"
echo "You can now upload this .zip directly to WordPress via Plugins > Add New > Upload Plugin."
