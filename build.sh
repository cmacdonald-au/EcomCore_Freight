#!/bin/sh

OUTPUT=/Volumes/Data/connect/src
FILE=EcomCore_Freight.tar

cd src
tar -cf ${OUTPUT}/${FILE} *

echo "Done: ${OUTPUT}/${FILE}"

