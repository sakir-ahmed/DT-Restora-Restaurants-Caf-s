#!/bin/bash
# Makes a rough placeholder voiceover (macOS Bengali voice "Piya"), one file per line of voiceover.txt,
# so build_video.py can time the animation. Replace it later with the real file at <reel>/audio/<ID>-voiceover.mp3.
# Usage: bash _tools/make_placeholder_voice.sh R01     (run it in a normal Terminal; the voice needs system speech access)
set -e
ID="$1"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="$(ls -d "$ROOT/$ID"-*/ | head -1)"
mkdir -p "$DIR/audio/placeholder"
rm -f "$DIR"/audio/placeholder/"$ID"-*.aiff
i=1
while IFS= read -r line; do
  [ -z "$line" ] && continue
  say -v Piya -r 170 -o "$DIR/audio/placeholder/$(printf '%s-%02d' "$ID" $i).aiff" "$line"
  i=$((i+1))
done < "$DIR/voiceover.txt"
echo "made $((i-1)) lines in $DIR/audio/placeholder"
